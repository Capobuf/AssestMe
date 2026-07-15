<?php

declare(strict_types=1);

use App\Actions\Evidence\DeleteEvidence;
use App\Actions\Evidence\StoreEvidenceFile;
use App\Actions\Evidence\StoreEvidenceUrl;
use App\Actions\Storage\AuditPrivateStorage;
use App\Enums\EvidenceType;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    Storage::fake('local');
    File::ensureDirectoryExists(evidenceFixtureDirectory());
});

afterEach(function (): void {
    File::deleteDirectory(evidenceFixtureDirectory());
});

it('accepts every approved evidence format using detected content MIME', function (string $extension, string $expectedMime): void {
    $finding = Finding::factory()->create();
    $evidence = app(StoreEvidenceFile::class)->handle(
        $finding,
        evidenceUpload($extension),
        ['title' => strtoupper($extension), 'include_in_report' => true],
    );

    expect($evidence->original_filename)->toBe("evidence.{$extension}")
        ->and($evidence->mime_type)->toBe($expectedMime);
    Storage::disk('local')->assertExists((string) $evidence->file_path);
})->with([
    'JPEG' => ['jpg', 'image/jpeg'],
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

    expect(fn () => app(StoreEvidenceFile::class)->handle(
        $finding,
        new UploadedFile($path, 'renamed.ods', null, null, true),
        ['title' => 'Archivio rinominato', 'include_in_report' => true],
    ))->toThrow(ValidationException::class);
});

it('stores generated private evidence files with a content hash and serves authorized previews', function (): void {
    $finding = Finding::factory()->create();
    $file = UploadedFile::fake()->image('rete.png', 640, 480);

    $evidence = app(StoreEvidenceFile::class)->handle($finding, $file, [
        'title' => 'Schema rete',
        'caption' => 'Topologia rilevata durante il sopralluogo.',
        'internal_notes' => null,
        'include_in_report' => true,
    ]);

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
    app(StoreEvidenceFile::class)->handle($finding, $file, [
        'title' => 'Prima evidenza',
        'include_in_report' => true,
    ]);

    expect(fn () => app(StoreEvidenceFile::class)->handle(
        $finding,
        new UploadedFile($file->getRealPath(), 'duplicata.png', 'image/png', null, true),
        ['title' => 'Duplicata', 'include_in_report' => true],
    ))->toThrow(ValidationException::class);

    $html = UploadedFile::fake()->createWithContent('pagina.html', '<script>alert(1)</script>');
    expect(fn () => app(StoreEvidenceFile::class)->handle(
        $finding,
        $html,
        ['title' => 'HTML', 'include_in_report' => true],
    ))->toThrow(ValidationException::class);

    expect(fn () => app(StoreEvidenceUrl::class)->handle($finding, [
        'title' => 'URL non valido',
        'url' => 'https://utente:segreto@example.test/prova',
        'include_in_report' => true,
    ]))->toThrow(ValidationException::class);
});

it('stores URL evidence without a file and archive deletion retains private data', function (): void {
    $finding = Finding::factory()->create();
    $urlEvidence = app(StoreEvidenceUrl::class)->handle($finding, [
        'title' => 'Console apparato',
        'url' => 'https://192.168.1.1/status',
        'include_in_report' => false,
    ]);

    expect($urlEvidence->type)->toBe(EvidenceType::Url)
        ->and($urlEvidence->file_path)->toBeNull();

    $fileEvidence = app(StoreEvidenceFile::class)->handle(
        $finding,
        UploadedFile::fake()->image('foto.jpg'),
        ['title' => 'Foto', 'include_in_report' => true],
    );
    app(DeleteEvidence::class)->handle($fileEvidence);

    expect($fileEvidence->fresh()?->trashed())->toBeTrue();
    Storage::disk('local')->assertExists((string) $fileEvidence->file_path);
});

it('reports missing corrupt and orphan evidence without deleting it', function (): void {
    $finding = Finding::factory()->create();
    $valid = app(StoreEvidenceFile::class)->handle(
        $finding,
        UploadedFile::fake()->image('valida.png'),
        ['title' => 'Valida', 'include_in_report' => true],
    );
    $missing = app(StoreEvidenceFile::class)->handle(
        $finding,
        UploadedFile::fake()->image('mancante.png', 37, 29),
        ['title' => 'Mancante', 'include_in_report' => true],
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
    $path = evidenceFixtureDirectory().'/evidence.'.$extension;

    match ($extension) {
        'jpg' => writeJpegEvidenceFixture($path),
        'png' => File::put($path, (string) file_get_contents(base_path('fixtures/evidence/valid-small.png'))),
        'webp' => writeWebpEvidenceFixture($path),
        'pdf' => File::put($path, (string) file_get_contents(base_path('fixtures/evidence/sample.pdf'))),
        'txt' => File::put($path, "plain evidence\n"),
        'log' => File::put($path, "2026-07-15 INFO evidence accepted\n"),
        'csv' => File::put($path, "name,value\nalpha,1\n"),
        'docx' => writeDocxEvidenceFixture($path),
        'xlsx', 'ods' => writeSpreadsheetEvidenceFixture($path, $extension),
        default => throw new InvalidArgumentException("Unsupported evidence test extension: {$extension}"),
    };

    return new UploadedFile($path, "evidence.{$extension}", null, null, true);
}

function writeWebpEvidenceFixture(string $path): int
{
    $image = imagecreatetruecolor(2, 2);
    if ($image === false || ! imagewebp($image, $path)) {
        throw new RuntimeException('The WebP evidence fixture could not be generated.');
    }
    imagedestroy($image);

    return 1;
}

function writeJpegEvidenceFixture(string $path): int
{
    $image = imagecreatetruecolor(2, 2);
    if ($image === false || ! imagejpeg($image, $path)) {
        throw new RuntimeException('The JPEG evidence fixture could not be generated.');
    }
    imagedestroy($image);

    return 1;
}

function writeDocxEvidenceFixture(string $path): int
{
    $archive = new ZipArchive;
    if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('The DOCX evidence fixture could not be generated.');
    }
    $archive->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $archive->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $archive->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Evidence</w:t></w:r></w:p></w:body></w:document>');
    $archive->close();

    return 1;
}

function writeSpreadsheetEvidenceFixture(string $path, string $extension): int
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setCellValue('A1', 'Evidence');
    $writer = $extension === 'xlsx' ? new Xlsx($spreadsheet) : new Ods($spreadsheet);
    $writer->save($path);
    $spreadsheet->disconnectWorksheets();

    return 1;
}
