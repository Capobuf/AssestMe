<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\Finding;
use App\Services\GoogleDrive\BuildGoogleDriveAssessmentSnapshot;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('builds a complete deterministic four-tab assessment projection', function (): void {
    [$assessment, $first, $second] = googleDriveSnapshotDataset();

    $snapshot = app(BuildGoogleDriveAssessmentSnapshot::class)->build($assessment, 'root-123');

    expect($snapshot->clientFolderName)->toStartWith('C-')
        ->and($snapshot->assessmentFolderName)->toStartWith('A-')
        ->and($snapshot->sheetName)->toBe(sprintf('Findings - A-%06d', $assessment->getKey()))
        ->and($snapshot->findingRows)->toHaveCount(3)
        ->and($snapshot->solutionRows)->toHaveCount(3)
        ->and($snapshot->evidences)->toHaveCount(2)
        ->and($snapshot->evidenceFiles)->toHaveCount(1)
        ->and($snapshot->reports)->toHaveCount(2)
        ->and($snapshot->findingRows[1][0])->toBe(sprintf('F-%06d', $first->getKey()))
        ->and($snapshot->findingRows[2][0])->toBe(sprintf('F-%06d', $second->getKey()))
        ->and($snapshot->contentHash)->toMatch('/^[a-f0-9]{64}$/');

    $repeat = app(BuildGoogleDriveAssessmentSnapshot::class)->build($assessment->fresh(), 'root-123');
    expect($repeat->contentHash)->toBe($snapshot->contentHash)
        ->and($repeat->assessmentRows(now()->addDay()))->not->toBe($snapshot->assessmentRows(now()))
        ->and($repeat->contentHash)->toBe($snapshot->contentHash);
});

it('changes the canonical hash for root and child changes', function (): void {
    [$assessment, $first] = googleDriveSnapshotDataset();
    $builder = app(BuildGoogleDriveAssessmentSnapshot::class);

    $initial = $builder->build($assessment, 'root-123')->contentHash;
    $otherRoot = $builder->build($assessment->fresh(), 'root-456')->contentHash;
    $first->update(['title' => 'Titolo locale aggiornato']);
    $changedFinding = $builder->build($assessment->fresh(), 'root-123')->contentHash;

    expect($otherRoot)->not->toBe($initial)
        ->and($changedFinding)->not->toBe($initial);
});

/** @return array{Assessment, Finding, Finding} */
function googleDriveSnapshotDataset(): array
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment IT completo',
        'assessment_date' => '2026-08-11',
        'introduction' => 'Introduzione leggibile',
        'executive_summary' => 'Sintesi leggibile',
        'methodology_notes' => 'Metodo leggibile',
    ]);
    $first = Finding::factory()->for($assessment)->create(['title' => 'Backup', 'sort_order' => 1]);
    $second = Finding::factory()->for($assessment)->create(['title' => 'Rete', 'sort_order' => 2]);

    foreach ([$first, $second] as $index => $finding) {
        $solution = $finding->solutions()->create([
            'external_key' => 'solution-'.($index + 1),
            'title' => 'Soluzione '.($index + 1),
            'description' => 'Descrizione soluzione '.($index + 1),
            'estimate_type' => EstimateType::Exact,
            'amount_min' => '100.00',
            'currency_code' => 'EUR',
            'billing_frequency' => BillingFrequency::OneOff,
            'sort_order' => 1,
        ]);
        $finding->update(['recommended_solution_id' => $solution->getKey()]);
    }

    $evidenceBytes = 'complete file evidence';
    Storage::disk('local')->put('evidence/complete.txt', $evidenceBytes);
    $first->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale tecnico',
        'file_path' => 'evidence/complete.txt',
        'original_filename' => 'verbale.txt',
        'caption' => 'Didascalia',
        'internal_notes' => 'Nota interna',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($evidenceBytes),
        'sha256' => hash('sha256', $evidenceBytes),
        'include_in_report' => true,
        'sort_order' => 1,
    ]);
    $second->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'Riferimento',
        'url' => 'https://example.test/reference',
        'include_in_report' => false,
        'sort_order' => 1,
    ]);

    foreach ([GeneratedReportFormat::Pdf, GeneratedReportFormat::Xlsx] as $format) {
        $bytes = $format->value.' report bytes';
        $path = 'reports/'.$assessment->getKey().'/'.$format->value.'-v1.'.$format->value;
        Storage::disk('local')->put($path, $bytes);
        $assessment->generatedReports()->create([
            'format' => $format,
            'version' => 1,
            'file_path' => $path,
            'file_name' => basename($path),
            'file_size_bytes' => strlen($bytes),
            'file_sha256' => hash('sha256', $bytes),
            'payload_sha256' => hash('sha256', '{}'),
            'payload_snapshot' => [],
            'settings_snapshot' => [],
            'generated_at' => now(),
        ]);
    }

    return [$assessment, $first, $second];
}
