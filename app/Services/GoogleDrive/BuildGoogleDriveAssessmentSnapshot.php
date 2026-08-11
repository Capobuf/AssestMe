<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Data\GoogleDrive\GoogleDriveAssessmentSnapshot;
use App\Data\GoogleDrive\GoogleDriveEvidenceSnapshotData;
use App\Data\GoogleDrive\GoogleDriveFileSnapshotData;
use App\Enums\AssessmentStatus;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\GeneratedReport;

final readonly class BuildGoogleDriveAssessmentSnapshot
{
    public function __construct(private GoogleDriveName $names) {}

    public function build(Assessment $assessment, string $rootFolderId): GoogleDriveAssessmentSnapshot
    {
        $assessment->refresh()->load([
            'client',
            'sites',
            'findings.category',
            'findings.sites',
            'findings.assets',
            'findings.consequenceLevel',
            'findings.likelihoodLevel',
            'findings.priorityLevel',
            'findings.solutions.effortLevel',
            'findings.evidences',
            'generatedReports',
        ]);

        $findings = $assessment->findings->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->values();

        $findingRows = [[
            'ID Finding', 'Ordine', 'Categoria', 'Titolo', 'Problema', 'Note imprenditore',
            'Note tecniche', 'Ambito', 'Descrizione ambito', 'Sedi', 'Asset', 'Conseguenza',
            'Probabilità', 'Priorità', 'Priorità sovrascritta', 'Motivazione priorità',
            'ID soluzione raccomandata', 'Soluzione raccomandata', 'ID soluzione implementata',
            'Soluzione implementata', 'Stato', 'Note risoluzione', 'Data risoluzione',
            'Incluso nel report',
        ]];
        $solutionRows = [[
            'ID Soluzione', 'ID Finding', 'Ordine', 'Titolo', 'Descrizione', 'Note confronto',
            'Effort', 'Note effort', 'Tipo stima', 'Importo minimo', 'Importo massimo', 'Valuta',
            'Frequenza fatturazione', 'Frequenza personalizzata', 'Note stima', 'Raccomandata',
            'Implementata',
        ]];
        $evidences = [];
        $evidenceFiles = [];

        foreach ($findings as $finding) {
            $findingRows[] = $this->findingRow($finding);
            foreach ($finding->solutions->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ]) as $solution) {
                $solutionRows[] = $this->solutionRow($finding, $solution);
            }
            foreach ($finding->evidences->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ]) as $evidence) {
                $remoteName = $evidence->type === EvidenceType::File
                    ? $this->names->evidence(
                        (int) $finding->getKey(),
                        (int) $evidence->getKey(),
                        $evidence->title,
                        $evidence->original_filename,
                    )
                    : null;
                $evidences[] = $this->evidence($finding, $evidence, $remoteName);
                if ($evidence->type === EvidenceType::File) {
                    $evidenceFiles[] = new GoogleDriveFileSnapshotData(
                        localId: (int) $evidence->getKey(),
                        remoteName: $remoteName ?? sprintf('E-%06d - Evidenza', $evidence->getKey()),
                        localPath: $evidence->file_path ?? '',
                        mimeType: $evidence->mime_type ?? 'application/octet-stream',
                        sizeBytes: $evidence->size_bytes ?? 0,
                        sha256: $evidence->sha256 ?? '',
                    );
                }
            }
        }

        $reports = $assessment->generatedReports
            ->sort(static function (GeneratedReport $left, GeneratedReport $right): int {
                return [$left->format->value, $left->version, $left->getKey()]
                    <=> [$right->format->value, $right->version, $right->getKey()];
            })
            ->values()
            ->map(fn (GeneratedReport $report): GoogleDriveFileSnapshotData => new GoogleDriveFileSnapshotData(
                localId: (int) $report->getKey(),
                remoteName: $this->names->generatedReport(
                    (int) $report->getKey(),
                    $report->format,
                    $report->version,
                ),
                localPath: $report->file_path,
                mimeType: $report->format->value === 'pdf'
                    ? 'application/pdf'
                    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                sizeBytes: $report->file_size_bytes,
                sha256: $report->file_sha256,
            ))
            ->all();

        $client = $assessment->client;

        return new GoogleDriveAssessmentSnapshot(
            assessmentId: (int) $assessment->getKey(),
            rootFolderId: $rootFolderId,
            clientFolderName: $this->names->client((int) $client->getKey(), $client->displayName()),
            assessmentFolderName: $this->names->assessment(
                (int) $assessment->getKey(),
                $assessment->assessment_date->format('Y-m-d'),
                $assessment->title,
            ),
            sheetName: $this->names->spreadsheet((int) $assessment->getKey()),
            assessmentValues: [
                ['ID Assessment', sprintf('A-%06d', $assessment->getKey())],
                ['Azienda', $client->displayName()],
                ['ID Azienda', sprintf('C-%06d', $client->getKey())],
                ['Titolo', $assessment->title],
                ['Data assessment', $assessment->assessment_date->format('Y-m-d')],
                ['Stato', AssessmentStatus::options()[$assessment->status->value]],
                ['Ambito', $this->scopeLabel($assessment->scope_type)],
                ['Descrizione ambito', $assessment->scope_description],
                ['Introduzione', $assessment->introduction],
                ['Sintesi esecutiva', $assessment->executive_summary],
                ['Note metodologia', $assessment->methodology_notes],
                ['Lingua', $assessment->locale],
            ],
            findingRows: $findingRows,
            solutionRows: $solutionRows,
            evidences: $evidences,
            evidenceFiles: $evidenceFiles,
            reports: $reports,
        );
    }

    /** @return list<string|int|bool|null> */
    private function findingRow(Finding $finding): array
    {
        $recommended = $finding->recommended_solution_id === null
            ? null
            : $finding->solutions->firstWhere('id', $finding->recommended_solution_id);
        $implemented = $finding->implemented_solution_id === null
            ? null
            : $finding->solutions->firstWhere('id', $finding->implemented_solution_id);

        return [
            sprintf('F-%06d', $finding->getKey()),
            $finding->sort_order,
            $finding->category?->name,
            $finding->title,
            $finding->problem,
            $finding->entrepreneur_notes,
            $finding->technical_notes,
            $this->scopeLabel($finding->scope_type),
            $finding->scope_description,
            $finding->sites->sortBy('id')->pluck('name')->implode(', '),
            $finding->assets->sortBy('id')->map(
                static fn (Asset $asset): string => sprintf('#%d %s', $asset->getKey(), $asset->name ?? 'Asset'),
            )->implode(', '),
            $finding->consequenceLevel?->label,
            $finding->likelihoodLevel?->label,
            $finding->priorityLevel?->label,
            $finding->priority_is_overridden,
            $finding->priority_rationale,
            $recommended instanceof FindingSolution ? sprintf('S-%06d', $recommended->getKey()) : null,
            $recommended instanceof FindingSolution ? $this->solutionSummary($recommended) : null,
            $implemented instanceof FindingSolution ? sprintf('S-%06d', $implemented->getKey()) : null,
            $implemented instanceof FindingSolution ? $this->solutionSummary($implemented) : null,
            FindingStatus::options()[$finding->status->value],
            $finding->resolution_notes,
            $finding->resolved_at?->format('Y-m-d'),
            $finding->include_in_report,
        ];
    }

    /** @return list<string|int|bool|null> */
    private function solutionRow(Finding $finding, FindingSolution $solution): array
    {
        return [
            sprintf('S-%06d', $solution->getKey()),
            sprintf('F-%06d', $finding->getKey()),
            $solution->sort_order,
            $solution->title,
            $solution->description,
            $solution->comparison_notes,
            $solution->effortLevel?->label,
            $solution->effort_notes,
            EstimateType::options()[$solution->estimate_type->value],
            $solution->amount_min,
            $solution->amount_max,
            $solution->currency_code,
            __('assestme.billing.'.$solution->billing_frequency->value),
            $solution->custom_billing_frequency,
            $solution->estimate_notes,
            $finding->recommended_solution_id === $solution->getKey(),
            $finding->implemented_solution_id === $solution->getKey(),
        ];
    }

    private function evidence(
        Finding $finding,
        Evidence $evidence,
        ?string $remoteName,
    ): GoogleDriveEvidenceSnapshotData {
        return new GoogleDriveEvidenceSnapshotData(
            id: (int) $evidence->getKey(),
            findingId: (int) $finding->getKey(),
            sortOrder: $evidence->sort_order,
            type: $evidence->type->value,
            title: $evidence->title,
            fileName: $remoteName,
            url: $evidence->url,
            originalFilename: $evidence->original_filename,
            caption: $evidence->caption,
            internalNotes: $evidence->internal_notes,
            mimeType: $evidence->mime_type,
            sizeBytes: $evidence->size_bytes,
            sha256: $evidence->sha256,
            included: $evidence->include_in_report,
        );
    }

    private function solutionSummary(FindingSolution $solution): string
    {
        return $solution->title."\n".$solution->description;
    }

    private function scopeLabel(ScopeType $scope): string
    {
        return __('assestme.scopes.'.$scope->value);
    }
}
