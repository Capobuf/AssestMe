<?php

declare(strict_types=1);

use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Evidence\DeleteEvidence;
use App\Actions\Evidence\InspectEvidenceUpload;
use App\Actions\Evidence\StoreEvidence;
use App\Actions\Storage\AuditPrivateStorage;
use App\Data\Assessments\FindingSaveData;
use App\Data\Evidence\PendingEvidenceFileData;
use App\Enums\AssessmentStatus;
use App\Enums\EvidenceType;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\User;
use App\Models\WorkspaceSaveRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

it('saves two evidence files in one idempotent finding operation and increments one version', function (): void {
    $finding = Finding::factory()->create(['title' => 'Finding prima del batch']);
    $requestId = (string) Str::uuid();
    $files = [
        app(InspectEvidenceUpload::class)->handle(UploadedFile::fake()->image('prima.png', 20, 20)),
        app(InspectEvidenceUpload::class)->handle(UploadedFile::fake()->image('seconda.png', 21, 21)),
    ];
    $request = aggregateEvidenceSaveRequest($finding, $files, $requestId, 'Finding salvato con due evidenze');

    $result = app(SaveFindingDetails::class)($finding, $request);
    $replay = app(SaveFindingDetails::class)($finding, $request);

    expect($result->appliedVersion)->toBe(1)
        ->and($result->evidenceIds)->toHaveCount(2)
        ->and($replay->idempotentReplay)->toBeTrue()
        ->and($replay->evidenceIds)->toBe($result->evidenceIds)
        ->and($finding->evidences()->count())->toBe(2)
        ->and($finding->assessment->fresh()->lock_version)->toBe(1)
        ->and(WorkspaceSaveRequest::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('rejects duplicate files inside one finding save before writing or mutating', function (): void {
    $finding = Finding::factory()->create(['title' => 'Finding invariato']);
    $upload = UploadedFile::fake()->image('contenuto.png', 22, 22);
    $files = [
        app(InspectEvidenceUpload::class)->handle($upload),
        app(InspectEvidenceUpload::class)->handle(new UploadedFile(
            $upload->getRealPath(),
            'duplicato.png',
            'image/png',
            null,
            true,
        )),
    ];

    expect(fn () => app(SaveFindingDetails::class)(
        $finding,
        aggregateEvidenceSaveRequest($finding, $files, (string) Str::uuid(), 'Titolo da non salvare'),
    ))->toThrow(ValidationException::class)
        ->and($finding->fresh()->title)->toBe('Finding invariato')
        ->and($finding->evidences()->count())->toBe(0)
        ->and($finding->assessment->fresh()->lock_version)->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects an invalid second upload before any aggregate write or finding mutation', function (): void {
    $finding = Finding::factory()->create(['title' => 'Finding invariato']);
    $files = [app(InspectEvidenceUpload::class)->handle(UploadedFile::fake()->image('prima.png', 20, 20))];
    $invalid = UploadedFile::fake()->createWithContent('seconda.exe', 'not an approved format');

    expect(fn () => $files[] = app(InspectEvidenceUpload::class)->handle($invalid))
        ->toThrow(ValidationException::class)
        ->and($finding->fresh()->title)->toBe('Finding invariato')
        ->and($finding->evidences()->count())->toBe(0)
        ->and($finding->assessment->fresh()->lock_version)->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('reports a disappeared prepared source without persistence success', function (): void {
    $finding = Finding::factory()->create(['title' => 'Finding invariato']);
    $upload = UploadedFile::fake()->image('temporanea.png', 20, 20);
    $pending = app(InspectEvidenceUpload::class)->handle($upload);
    unlink($upload->getRealPath());

    expect(fn () => app(SaveFindingDetails::class)(
        $finding,
        aggregateEvidenceSaveRequest($finding, [$pending], (string) Str::uuid(), 'Titolo non persistito'),
    ))->toThrow(RuntimeException::class)
        ->and($finding->fresh()->title)->toBe('Finding invariato')
        ->and($finding->evidences()->count())->toBe(0)
        ->and($finding->assessment->fresh()->lock_version)->toBe(0)
        ->and(WorkspaceSaveRequest::query()->count())->toBe(0);
});

it('compensates every prepared batch file when evidence database persistence fails', function (): void {
    $finding = Finding::factory()->create(['title' => 'Finding invariato']);
    $files = [
        app(InspectEvidenceUpload::class)->handle(UploadedFile::fake()->image('prima.png', 23, 23)),
        app(InspectEvidenceUpload::class)->handle(UploadedFile::fake()->image('seconda.png', 24, 24)),
    ];
    Event::listen('eloquent.creating: '.Evidence::class, static function (): never {
        throw new RuntimeException('Forced aggregate evidence persistence failure.');
    });

    expect(fn () => app(SaveFindingDetails::class)(
        $finding,
        aggregateEvidenceSaveRequest($finding, $files, (string) Str::uuid(), 'Titolo da non salvare'),
    ))->toThrow(RuntimeException::class)
        ->and($finding->fresh()->title)->toBe('Finding invariato')
        ->and($finding->evidences()->count())->toBe(0)
        ->and($finding->assessment->fresh()->lock_version)->toBe(0)
        ->and(WorkspaceSaveRequest::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rejects reuse of one finding request id with a different aggregate payload', function (): void {
    $finding = Finding::factory()->create();
    $requestId = (string) Str::uuid();
    $first = aggregateEvidenceSaveRequest($finding, [], $requestId, 'Prima versione');
    app(SaveFindingDetails::class)($finding, $first);
    $different = aggregateEvidenceSaveRequest($finding->fresh(), [], $requestId, 'Payload differente');

    expect(fn () => app(SaveFindingDetails::class)($finding->fresh(), $different))
        ->toThrow(IdempotencyKeyMismatch::class)
        ->and($finding->fresh()->title)->toBe('Prima versione')
        ->and($finding->assessment->fresh()->lock_version)->toBe(1)
        ->and(WorkspaceSaveRequest::query()->count())->toBe(1);
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

/**
 * @param  list<PendingEvidenceFileData>  $files
 */
function aggregateEvidenceSaveRequest(
    Finding $finding,
    array $files,
    string $requestId,
    string $title,
): FindingSaveData {
    $findingPayload = [
        'title' => $title,
        'problem' => $finding->problem,
        'entrepreneur_notes' => $finding->entrepreneur_notes,
        'technical_notes' => $finding->technical_notes,
        'category_id' => $finding->category_id,
        'scope_type' => $finding->scope_type?->value ?? 'organization',
        'scope_description' => $finding->scope_description,
        'site_ids' => [],
        'asset_ids' => [],
        'consequence_level_id' => $finding->consequence_level_id,
        'likelihood_level_id' => $finding->likelihood_level_id,
        'priority_level_id' => $finding->priority_level_id,
        'priority_is_overridden' => (bool) $finding->priority_is_overridden,
        'priority_rationale' => $finding->priority_rationale,
        'status' => $finding->status?->value ?? 'open',
        'include_in_report' => (bool) ($finding->include_in_report ?? true),
        'resolution_notes' => $finding->resolution_notes,
        'solutions' => [],
    ];
    $payload = FindingSaveData::aggregatePayload($findingPayload, $files, null);

    return new FindingSaveData(
        requestId: $requestId,
        expectedVersion: (int) $finding->assessment->lock_version,
        tabId: (string) Str::uuid(),
        payload: $payload,
        payloadSha256: FindingSaveData::hashPayload($payload),
        evidenceFiles: $files,
    );
}
