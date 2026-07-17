<?php

declare(strict_types=1);

use App\Actions\Evidence\DeleteEvidence;
use App\Actions\Evidence\StoreEvidence;
use App\Actions\Storage\AuditPrivateStorage;
use App\Enums\AssessmentStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
    File::ensureDirectoryExists(evidenceFixtureDirectory());
});

afterEach(function (): void {
    File::deleteDirectory(evidenceFixtureDirectory());
});

it('accepts every approved evidence format using detected content MIME', function (string $extension, string $expectedMime): void {
    $finding = Finding::factory()->create();
    $evidence = app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => strtoupper($extension), 'include_in_report' => true],
        evidenceUpload($extension),
    );

    expect($evidence->original_filename)->toBe("evidence.{$extension}")
        ->and($evidence->mime_type)->toBe($expectedMime);
    Storage::disk('local')->assertExists((string) $evidence->file_path);
})->with([
    'JPEG' => ['jpg', 'image/jpeg'],
    'JPEG long extension' => ['jpeg', 'image/jpeg'],
    'PNG' => ['png', 'image/png'],
    'WebP' => ['webp', 'image/webp'],
    'PDF' => ['pdf', 'application/pdf'],
    'text' => ['txt', 'text/plain'],
    'log' => ['log', 'text/plain'],
    'CSV detected as text' => ['csv', 'text/plain'],
    'DOCX' => ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'XLSX' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'ODS detected as ZIP' => ['ods', 'application/vnd.oasis.opendocument.spreadsheet'],
]);

it('rejects a generic ZIP renamed as an approved office document', function (): void {
    $finding = Finding::factory()->create();
    $path = evidenceFixtureDirectory().'/renamed.ods';
    $archive = new ZipArchive;
    expect($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $archive->addFromString('payload.txt', 'not an OpenDocument spreadsheet');
    $archive->close();

    expect(fn () => app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Archivio rinominato', 'include_in_report' => true],
        new UploadedFile($path, 'renamed.ods', null, null, true),
    ))->toThrow(ValidationException::class);
});

it('stores generated private evidence files with a content hash and serves authorized previews', function (): void {
    $finding = Finding::factory()->create();
    $file = UploadedFile::fake()->image('rete.png', 640, 480);

    $evidence = app(StoreEvidence::class)($finding, EvidenceType::File, [
        'title' => 'Schema rete',
        'caption' => 'Topologia rilevata durante il sopralluogo.',
        'internal_notes' => null,
        'include_in_report' => true,
    ], $file);

    expect($evidence->type)->toBe(EvidenceType::File)
        ->and($evidence->original_filename)->toBe('rete.png')
        ->and($evidence->file_path)->not->toContain('rete.png')
        ->and($evidence->sha256)->toHaveLength(64);
    Storage::disk('local')->assertExists((string) $evidence->file_path);

    $this->get(route('evidence.download', $evidence))->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create())
        ->get(route('evidence.download', $evidence))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'image/png');
});

it('rejects mismatched types, duplicate content, and credential-bearing URLs', function (): void {
    $finding = Finding::factory()->create();
    $file = UploadedFile::fake()->image('evidenza.png');
    app(StoreEvidence::class)($finding, EvidenceType::File, [
        'title' => 'Prima evidenza',
        'include_in_report' => true,
    ], $file);

    expect(fn () => app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Duplicata', 'include_in_report' => true],
        new UploadedFile($file->getRealPath(), 'duplicata.png', 'image/png', null, true),
    ))->toThrow(ValidationException::class);

    $html = UploadedFile::fake()->createWithContent('pagina.html', '<script>alert(1)</script>');
    expect(fn () => app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'HTML', 'include_in_report' => true],
        $html,
    ))->toThrow(ValidationException::class);

    expect(fn () => app(StoreEvidence::class)($finding, EvidenceType::Url, [
        'title' => 'URL non valido',
        'url' => 'https://utente:segreto@example.test/prova',
        'include_in_report' => true,
    ]))->toThrow(ValidationException::class);
});

it('compensates a failed file record and rejects persisted read-only state', function (): void {
    $finding = Finding::factory()->create();
    Event::listen('eloquent.creating: '.Evidence::class, static function (): never {
        throw new RuntimeException('Forced evidence persistence failure.');
    });

    expect(fn () => app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Compensata', 'include_in_report' => true],
        UploadedFile::fake()->image('compensata.png'),
    ))->toThrow(RuntimeException::class)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();

    Event::forget('eloquent.creating: '.Evidence::class);
    $finding->assessment()->update(['status' => AssessmentStatus::Completed]);

    expect(fn () => app(StoreEvidence::class)($finding, EvidenceType::Url, [
        'title' => 'Bloccata',
        'url' => 'https://example.test/evidence',
        'include_in_report' => true,
    ]))->toThrow(ValidationException::class)
        ->and(Evidence::query()->count())->toBe(0);
});

it('stores URL evidence without a file and archive deletion retains private data', function (): void {
    $finding = Finding::factory()->create();
    $urlEvidence = app(StoreEvidence::class)($finding, EvidenceType::Url, [
        'title' => 'Console apparato',
        'url' => 'https://192.168.1.1/status',
        'include_in_report' => false,
    ]);

    expect($urlEvidence->type)->toBe(EvidenceType::Url)
        ->and($urlEvidence->file_path)->toBeNull();

    $fileEvidence = app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Foto', 'include_in_report' => true],
        UploadedFile::fake()->image('foto.jpg'),
    );
    app(DeleteEvidence::class)($fileEvidence);

    expect($fileEvidence->fresh()?->trashed())->toBeTrue();
    Storage::disk('local')->assertExists((string) $fileEvidence->file_path);
});

it('reports missing corrupt and orphan evidence without deleting it', function (): void {
    $finding = Finding::factory()->create();
    $valid = app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Valida', 'include_in_report' => true],
        UploadedFile::fake()->image('valida.png'),
    );
    $missing = app(StoreEvidence::class)(
        $finding,
        EvidenceType::File,
        ['title' => 'Mancante', 'include_in_report' => true],
        UploadedFile::fake()->image('mancante.png', 37, 29),
    );
    Storage::disk('local')->delete((string) $missing->file_path);
    Storage::disk('local')->put((string) $valid->file_path, 'contenuto alterato');
    $orphan = 'clients/1/assessments/1/findings/1/evidence/orfano.log';
    Storage::disk('local')->put($orphan, 'file orfano');

    $result = app(AuditPrivateStorage::class)->handle();
    expect($result->orphanFiles)->toContain($orphan)
        ->and($result->missingFiles)->toContain("evidence:{$missing->id}")
        ->and($result->hashMismatches)->toContain("evidence:{$valid->id}")
        ->and($result->hasIssues())->toBeTrue();
    Storage::disk('local')->assertExists($orphan);

    $this->artisan('assestme:storage:audit')->assertFailed();
});

function evidenceFixtureDirectory(): string
{
    return storage_path('framework/testing/evidence-formats');
}

function evidenceUpload(string $extension): UploadedFile
{
    $filename = match ($extension) {
        'png' => 'valid-small.png',
        default => "sample.{$extension}",
    };
    $path = base_path('fixtures/evidence/'.$filename);

    if (! is_file($path)) {
        throw new RuntimeException("The committed evidence fixture is missing: {$filename}");
    }

    return new UploadedFile($path, "evidence.{$extension}", null, null, true);
}
