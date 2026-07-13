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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
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
