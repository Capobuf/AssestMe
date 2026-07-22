<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\BuildAssessmentSnapshot;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Enums\EvidenceType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
    Storage::fake('local');
});

it('preserves mixed evidence order image proportions captions and the immutable snapshot', function (): void {
    [$assessment, $finding] = editorialEvidencePdfReadyAssessment();

    $verticalPng = editorialEvidencePdfPng(320, 900, [32, 55, 88]);
    $horizontalPng = editorialEvidencePdfPng(900, 320, [88, 55, 32]);
    $verticalInfo = getimagesizefromstring($verticalPng);
    $horizontalInfo = getimagesizefromstring($horizontalPng);

    expect($verticalInfo)->toBeArray()
        ->and([$verticalInfo[0], $verticalInfo[1]])->toBe([320, 900])
        ->and($horizontalInfo)->toBeArray()
        ->and([$horizontalInfo[0], $horizontalInfo[1]])->toBe([900, 320]);

    $verticalPath = 'evidence/editorial/vertical.png';
    $documentPath = 'evidence/editorial/internal-document.pdf';
    $horizontalPath = 'evidence/editorial/horizontal.png';
    $documentContents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    Storage::disk('local')->put($verticalPath, $verticalPng);
    Storage::disk('local')->put($documentPath, $documentContents);
    Storage::disk('local')->put($horizontalPath, $horizontalPng);

    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'ARMADIO-VERTICALE.PNG',
        'file_path' => $verticalPath,
        'original_filename' => 'armadio-verticale.png',
        'caption' => 'Didascalia verticale visibile',
        'mime_type' => 'image/png',
        'size_bytes' => strlen($verticalPng),
        'sha256' => hash('sha256', $verticalPng),
        'include_in_report' => true,
        'sort_order' => 10,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'Procedura continuità online',
        'url' => 'https://example.test/editorial-evidence',
        'include_in_report' => true,
        'sort_order' => 20,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'Evidenza esclusa dal report',
        'url' => 'https://example.test/excluded-evidence',
        'include_in_report' => false,
        'sort_order' => 25,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale tecnico PDF',
        'file_path' => $documentPath,
        'original_filename' => 'verbale-tecnico.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($documentContents),
        'sha256' => hash('sha256', $documentContents),
        'include_in_report' => true,
        'sort_order' => 30,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Vista orizzontale sala',
        'file_path' => $horizontalPath,
        'original_filename' => 'sala-orizzontale.png',
        'caption' => 'Didascalia orizzontale visibile',
        'mime_type' => 'image/png',
        'size_bytes' => strlen($horizontalPng),
        'sha256' => hash('sha256', $horizontalPng),
        'include_in_report' => true,
        'sort_order' => 40,
    ]);

    $settings = app(ReportSettings::class);
    $settings->cover = false;
    $settings->content_index = false;
    $settings->executive_summary = false;
    $settings->risk_legend = false;
    $settings->summary_table = false;
    $settings->methodology = false;
    $settings->disclaimer = false;
    $settings->signature_block = false;
    $settings->repeated_header_footer = false;
    $settings->page_numbers = false;
    $settings->evidence = true;
    $settings->evidence_captions = true;
    $settings->save();

    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();
    $imageTagCount = preg_match_all('/<img class="evidence-image"[^>]*>/u', $html, $imageTags);
    $normalizedHtml = editorialEvidencePdfNormalize($html);

    expect($imageTagCount)->toBe(2)
        ->and($imageTags[0])->toHaveCount(2)
        ->and($imageTags[0][0])->not->toMatch('/\s(?:width|height)\s*=/iu')
        ->and($imageTags[0][1])->not->toMatch('/\s(?:width|height)\s*=/iu')
        ->and($normalizedHtml)->toMatch('/\.evidence-image\s*\{[^}]*height:\s*auto;[^}]*max-height:\s*145mm;[^}]*max-width:\s*100%;[^}]*width:\s*auto;[^}]*\}/u')
        ->and($normalizedHtml)->not->toContain('object-fit: cover');

    $withCaptions = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $withCaptionsPath = Storage::disk('local')->path($withCaptions->file_path);
    $withCaptionsContents = file_get_contents($withCaptionsPath);
    $parsed = (new Parser)->parseFile($withCaptionsPath);
    $normalizedText = editorialEvidencePdfNormalize($parsed->getText());

    expect($withCaptionsContents)->toBeString()
        ->and(substr($withCaptionsContents, 0, 5))->toBe('%PDF-')
        ->and($parsed->getPages())->not->toBeEmpty()
        ->and($normalizedText)->toContain(
            'Didascalia verticale visibile',
            'Didascalia orizzontale visibile',
            'https://example.test/editorial-evidence',
            'verbale-tecnico.pdf',
            'application/pdf',
        )
        ->and($normalizedText)->not->toContain(
            'ARMADIO-VERTICALE.PNG',
            'Evidenza esclusa dal report',
            'https://example.test/excluded-evidence',
            $verticalPath,
            $documentPath,
            $horizontalPath,
        )
        ->and($withCaptionsContents)->toContain('https://example.test/editorial-evidence')
        ->and($withCaptionsContents)->not->toContain($verticalPath, $documentPath, $horizontalPath);

    $orderedTokens = [
        'IMMAGINE 01',
        'LINK 02',
        'Procedura continuità online',
        'FILE 03',
        'Verbale tecnico PDF',
        'IMMAGINE 04',
        'Vista orizzontale sala',
    ];
    $lastPosition = -1;
    foreach ($orderedTokens as $token) {
        $position = mb_strpos($normalizedText, $token);
        expect($position)->not->toBeFalse();
        expect((int) $position)->toBeGreaterThan($lastPosition);
        $lastPosition = (int) $position;
    }

    $evidenceSnapshot = $withCaptions->payload_snapshot['findings'][0]['evidences'];
    expect(array_column($evidenceSnapshot, 'title'))->toBe([
        'ARMADIO-VERTICALE.PNG',
        'Procedura continuità online',
        'Evidenza esclusa dal report',
        'Verbale tecnico PDF',
        'Vista orizzontale sala',
    ])->and(array_column($evidenceSnapshot, 'sort_order'))->toBe([10, 20, 25, 30, 40])
        ->and(array_column($evidenceSnapshot, 'included'))->toBe([true, true, false, true, true])
        ->and(array_column($evidenceSnapshot, 'type'))->toBe(['file', 'url', 'url', 'file', 'file'])
        ->and(array_column($evidenceSnapshot, 'file_path'))->toBe([$verticalPath, null, null, $documentPath, $horizontalPath])
        ->and(array_column($evidenceSnapshot, 'url'))->toBe([
            null,
            'https://example.test/editorial-evidence',
            'https://example.test/excluded-evidence',
            null,
            null,
        ])->and(array_column($evidenceSnapshot, 'caption'))->toBe([
            'Didascalia verticale visibile',
            null,
            null,
            null,
            'Didascalia orizzontale visibile',
        ])->and($evidenceSnapshot[0])->toMatchArray([
            'original_filename' => 'armadio-verticale.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($verticalPng),
            'sha256' => hash('sha256', $verticalPng),
        ])->and($evidenceSnapshot[3])->toMatchArray([
            'original_filename' => 'verbale-tecnico.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($documentContents),
            'sha256' => hash('sha256', $documentContents),
        ]);

    $settings->evidence_captions = false;
    $settings->save();

    $withoutCaptions = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $withoutCaptionsPath = Storage::disk('local')->path($withoutCaptions->file_path);
    $withoutCaptionsContents = file_get_contents($withoutCaptionsPath);
    $withoutCaptionsText = editorialEvidencePdfNormalize((new Parser)->parseFile($withoutCaptionsPath)->getText());

    expect($withoutCaptionsContents)->toBeString()
        ->and(substr($withoutCaptionsContents, 0, 5))->toBe('%PDF-')
        ->and($withoutCaptions->settings_snapshot['evidence_captions'])->toBeFalse()
        ->and($withoutCaptionsText)->toContain('Vista orizzontale sala')
        ->and($withoutCaptionsText)->not->toContain('ARMADIO-VERTICALE.PNG', 'Didascalia verticale visibile', 'Didascalia orizzontale visibile')
        ->and(GeneratedReport::query()->where('assessment_id', $assessment->getKey())->count())->toBe(2);
});

/** @return array{Assessment, Finding} */
function editorialEvidencePdfReadyAssessment(): array
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment evidenze editoriali',
        'introduction' => null,
        'executive_summary' => null,
        'methodology_notes' => null,
    ]);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update([
        'title' => 'Continuità operativa non garantita',
        'scope_type' => ScopeType::Organization,
        'scope_description' => null,
    ]);

    return [$assessment->fresh(), $finding->fresh()];
}

/** @param array{int, int, int} $background */
function editorialEvidencePdfPng(int $width, int $height, array $background): string
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create the evidence image fixture.');
    }

    $backgroundColor = imagecolorallocate($image, $background[0], $background[1], $background[2]);
    $lineColor = imagecolorallocate($image, 240, 240, 235);
    imagefill($image, 0, 0, $backgroundColor);
    imageline($image, 0, 0, $width - 1, $height - 1, $lineColor);
    imageline($image, $width - 1, 0, 0, $height - 1, $lineColor);

    ob_start();
    $encoded = imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    if (! $encoded || ! is_string($contents)) {
        throw new RuntimeException('Unable to encode the evidence image fixture.');
    }

    return $contents;
}

function editorialEvidencePdfNormalize(string $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}
