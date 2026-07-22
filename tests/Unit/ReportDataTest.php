<?php

declare(strict_types=1);

use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\ReportEvidenceData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportPriorityData;

/**
 * @param  list<ReportEvidenceData>  $evidences
 */
function makeReportFindingData(int $id, string $priorityLabel, array $evidences = []): ReportFindingData
{
    return new ReportFindingData(
        id: $id,
        number: $id,
        includeInReport: true,
        title: "Finding {$id}",
        category: '',
        scopeType: 'whole_company',
        scopeLabel: 'Intera azienda',
        scopeDescription: null,
        sites: [],
        assets: [],
        consequenceLabel: null,
        consequenceColor: null,
        likelihoodLabel: null,
        likelihoodColor: null,
        priorityLabel: $priorityLabel,
        priorityColor: '#111111',
        priorityOverridden: false,
        priorityRationale: null,
        problem: 'Problema',
        entrepreneurNotes: null,
        technicalNotes: null,
        status: 'open',
        statusLabel: 'Aperto',
        solutions: [],
        evidences: $evidences,
        resolutionNotes: null,
        resolvedAt: null,
        resolvedAtLabel: null,
    );
}

/**
 * @param  list<ReportPriorityData>  $priorityLegend
 * @param  list<ReportFindingData>  $findings
 */
function makeAssessmentReportData(array $priorityLegend, array $findings): AssessmentReportData
{
    return new AssessmentReportData(
        generatedAt: '2026-07-22T10:00:00+00:00',
        applicationVersion: 'test',
        locale: 'it',
        title: 'Assessment IT',
        assessmentId: 1,
        assessmentTitle: 'Assessment IT',
        assessmentDate: '2026-07-22',
        assessmentDateLabel: '22/07/2026',
        assessmentStatus: 'draft',
        assessmentStatusLabel: 'Bozza',
        assessmentScope: 'Intera azienda',
        scopeDescription: null,
        assessmentSites: [],
        introduction: null,
        executiveSummary: null,
        methodologyNotes: null,
        clientId: 1,
        clientName: 'Azienda Demo S.r.l.',
        clientLegalName: 'Azienda Demo S.r.l.',
        clientVatNumber: null,
        clientTaxCode: null,
        clientEmail: null,
        clientPhone: null,
        clientWebsite: null,
        clientAddress: null,
        logos: [],
        priorityLegend: $priorityLegend,
        findings: $findings,
        settingsSnapshot: [],
    );
}

function makeReportEvidenceData(int $id, string $type, bool $included, ?string $imageDataUri = null): ReportEvidenceData
{
    return new ReportEvidenceData(
        id: $id,
        type: $type,
        title: "Evidenza {$id}",
        filePath: $type === 'file' ? "evidence/{$id}.bin" : null,
        url: $type === 'url' ? "https://example.test/evidence/{$id}" : null,
        originalFilename: $type === 'file' ? "evidence-{$id}.bin" : null,
        caption: null,
        mimeType: $type === 'file' ? 'application/octet-stream' : null,
        sizeBytes: $type === 'file' ? 100 : null,
        sha256: $type === 'file' ? str_repeat('a', 64) : null,
        included: $included,
        sortOrder: $id,
        imageDataUri: $imageDataUri,
    );
}

it('counts priorities in legend order and excludes levels without findings', function (): void {
    $report = makeAssessmentReportData(
        priorityLegend: [
            new ReportPriorityData('medium', 'Media', null, '#F59E0B', 1),
            new ReportPriorityData('low', 'Bassa', null, '#22C55E', 2),
            new ReportPriorityData('high', 'Alta', null, '#DC2626', 3),
        ],
        findings: [
            makeReportFindingData(1, 'Alta'),
            makeReportFindingData(2, 'Media'),
            makeReportFindingData(3, 'Media'),
        ],
    );

    expect($report->priorityCounts())->toBe([
        ['label' => 'Media', 'color' => '#F59E0B', 'count' => 2],
        ['label' => 'Alta', 'color' => '#DC2626', 'count' => 1],
    ]);
});

it('returns included evidence in the original mixed-type order', function (): void {
    $finding = makeReportFindingData(1, 'Alta', [
        makeReportEvidenceData(1, 'file', true, 'data:image/png;base64,aW1hZ2U='),
        makeReportEvidenceData(2, 'url', true),
        makeReportEvidenceData(3, 'file', false),
        makeReportEvidenceData(4, 'file', true),
        makeReportEvidenceData(5, 'file', true, 'data:image/jpeg;base64,aW1hZ2U='),
    ]);

    $includedEvidence = $finding->includedEvidence();

    expect(array_map(static fn (ReportEvidenceData $evidence): int => $evidence->id, $includedEvidence))
        ->toBe([1, 2, 4, 5])
        ->and(array_map(static fn (ReportEvidenceData $evidence): string => $evidence->type, $includedEvidence))
        ->toBe(['file', 'url', 'file', 'file'])
        ->and($includedEvidence[0]->isImage())->toBeTrue()
        ->and($includedEvidence[1]->isImage())->toBeFalse()
        ->and($includedEvidence[2]->isImage())->toBeFalse()
        ->and($includedEvidence[3]->isImage())->toBeTrue();
});
