<?php

declare(strict_types=1);

namespace App\Data\Reports;

/**
 * @phpstan-type ScalarValue bool|float|int|string|null
 * @phpstan-type LevelOne ScalarValue|array<array-key, ScalarValue>
 * @phpstan-type LevelTwo ScalarValue|array<array-key, LevelOne>
 * @phpstan-type LevelThree ScalarValue|array<array-key, LevelTwo>
 * @phpstan-type LevelFour ScalarValue|array<array-key, LevelThree>
 * @phpstan-type LevelFive ScalarValue|array<array-key, LevelFour>
 */
final readonly class AssessmentReportData
{
    /**
     * @param  list<string>  $assessmentSites
     * @param  list<ReportLogoData>  $logos
     * @param  list<ReportPriorityData>  $priorityLegend
     * @param  list<ReportFindingData>  $findings
     * @param  array<string, bool|int|string|null>  $settingsSnapshot
     */
    public function __construct(
        public string $generatedAt,
        public string $applicationVersion,
        public string $locale,
        public string $title,
        public int $assessmentId,
        public string $assessmentTitle,
        public string $assessmentDate,
        public string $assessmentDateLabel,
        public string $assessmentStatus,
        public string $assessmentStatusLabel,
        public string $assessmentScope,
        public ?string $scopeDescription,
        public array $assessmentSites,
        public ?string $introduction,
        public ?string $executiveSummary,
        public ?string $methodologyNotes,
        public int $clientId,
        public string $clientName,
        public string $clientLegalName,
        public ?string $clientVatNumber,
        public ?string $clientTaxCode,
        public ?string $clientEmail,
        public ?string $clientPhone,
        public ?string $clientWebsite,
        public ?string $clientAddress,
        public array $logos,
        public array $priorityLegend,
        public array $findings,
        public array $settingsSnapshot,
    ) {}

    public function setting(string $key): bool|int|string|null
    {
        return $this->settingsSnapshot[$key] ?? null;
    }

    /** @return list<ReportEvidenceData> */
    public function attachments(): array
    {
        $attachments = [];
        foreach ($this->findings as $finding) {
            array_push($attachments, ...$finding->includedFileEvidence());
        }

        return $attachments;
    }

    /** @return array<string, LevelFive> */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'generated_at' => $this->generatedAt,
            'application_version' => $this->applicationVersion,
            'locale' => $this->locale,
            'title' => $this->title,
            'assessment' => [
                'id' => $this->assessmentId,
                'title' => $this->assessmentTitle,
                'assessment_date' => $this->assessmentDate,
                'assessment_date_label' => $this->assessmentDateLabel,
                'status' => $this->assessmentStatus,
                'status_label' => $this->assessmentStatusLabel,
                'scope' => $this->assessmentScope,
                'scope_description' => $this->scopeDescription,
                'sites' => $this->assessmentSites,
                'introduction' => $this->introduction,
                'executive_summary' => $this->executiveSummary,
                'methodology_notes' => $this->methodologyNotes,
            ],
            'client' => [
                'id' => $this->clientId,
                'name' => $this->clientName,
                'legal_name' => $this->clientLegalName,
                'vat_number' => $this->clientVatNumber,
                'tax_code' => $this->clientTaxCode,
                'email' => $this->clientEmail,
                'phone' => $this->clientPhone,
                'website' => $this->clientWebsite,
                'address' => $this->clientAddress,
            ],
            'logos' => array_map(static fn (ReportLogoData $logo): array => $logo->toArray(), $this->logos),
            'priority_legend' => array_map(static fn (ReportPriorityData $priority): array => $priority->toArray(), $this->priorityLegend),
            'findings' => array_map(static fn (ReportFindingData $finding): array => $finding->toArray(), $this->findings),
            'settings' => $this->settingsSnapshot,
        ];
    }
}
