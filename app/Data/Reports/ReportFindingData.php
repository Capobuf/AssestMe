<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportFindingData
{
    /**
     * @param  list<string>  $sites
     * @param  list<ReportAssetData>  $assets
     * @param  list<ReportSolutionData>  $solutions
     * @param  list<ReportEvidenceData>  $evidences
     */
    public function __construct(
        public int $id,
        public int $number,
        public bool $includeInReport,
        public string $title,
        public string $category,
        public string $scopeType,
        public string $scopeLabel,
        public ?string $scopeDescription,
        public array $sites,
        public array $assets,
        public ?string $consequenceLabel,
        public ?string $consequenceColor,
        public ?string $likelihoodLabel,
        public ?string $likelihoodColor,
        public string $priorityLabel,
        public string $priorityColor,
        public bool $priorityOverridden,
        public ?string $priorityRationale,
        public string $problem,
        public ?string $entrepreneurNotes,
        public ?string $technicalNotes,
        public string $status,
        public string $statusLabel,
        public array $solutions,
        public array $evidences,
        public ?string $resolutionNotes,
        public ?string $resolvedAt,
        public ?string $resolvedAtLabel,
        public FindingPagePlanData $pagePlan,
        public ?ReportRiskMatrixData $riskMatrix,
    ) {}

    public function recommendedSolution(): ReportSolutionData
    {
        $solution = $this->recommendedSolutionOrNull();
        if ($solution instanceof ReportSolutionData) {
            return $solution;
        }

        throw new \LogicException('A report finding must contain one recommended solution.');
    }

    public function recommendedSolutionOrNull(): ?ReportSolutionData
    {
        foreach ($this->solutions as $solution) {
            if ($solution->recommended) {
                return $solution;
            }
        }

        return null;
    }

    /** @return list<ReportSolutionData> */
    public function alternativeSolutions(): array
    {
        return array_values(array_filter(
            $this->solutions,
            static fn (ReportSolutionData $solution): bool => ! $solution->recommended,
        ));
    }

    public function implementedSolution(): ?ReportSolutionData
    {
        foreach ($this->solutions as $solution) {
            if ($solution->implemented) {
                return $solution;
            }
        }

        return null;
    }

    public function primarySolution(): ReportSolutionData
    {
        return $this->implementedSolution() ?? $this->recommendedSolution();
    }

    public function problemExcerpt(): string
    {
        return $this->excerpt($this->problem, 320);
    }

    public function primarySolutionExcerpt(): string
    {
        return $this->excerpt($this->primarySolution()->description, 280);
    }

    /**
     * @param  list<int>  $ids
     * @return list<ReportSolutionData>
     */
    public function solutionsByIds(array $ids): array
    {
        $positions = array_flip($ids);
        $solutions = array_values(array_filter(
            $this->solutions,
            static fn (ReportSolutionData $solution): bool => isset($positions[$solution->id]),
        ));

        usort(
            $solutions,
            static fn (ReportSolutionData $left, ReportSolutionData $right): int => $positions[$left->id] <=> $positions[$right->id],
        );

        return $solutions;
    }

    private function excerpt(string $text, int $limit): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        $candidate = mb_substr($normalized, 0, $limit + 1);
        $sentenceEnd = max(
            mb_strrpos(mb_substr($candidate, 0, $limit), '.') ?: 0,
            mb_strrpos(mb_substr($candidate, 0, $limit), '!') ?: 0,
            mb_strrpos(mb_substr($candidate, 0, $limit), '?') ?: 0,
        );
        if ($sentenceEnd >= (int) floor($limit * 0.6)) {
            return rtrim(mb_substr($candidate, 0, $sentenceEnd + 1));
        }

        $wordEnd = mb_strrpos(mb_substr($candidate, 0, $limit), ' ');
        $cut = $wordEnd === false ? $limit : $wordEnd;

        return rtrim(mb_substr($candidate, 0, $cut)).'…';
    }

    /** @return list<ReportEvidenceData> */
    public function includedEvidence(): array
    {
        return array_values(array_filter(
            $this->evidences,
            static fn (ReportEvidenceData $evidence): bool => $evidence->included,
        ));
    }

    /** @return list<ReportEvidenceData> */
    public function includedImageEvidence(): array
    {
        return array_values(array_filter(
            $this->evidences,
            static fn (ReportEvidenceData $evidence): bool => $evidence->included && $evidence->isImage(),
        ));
    }

    /** @return list<ReportEvidenceData> */
    public function includedFileEvidence(): array
    {
        return array_values(array_filter(
            $this->evidences,
            static fn (ReportEvidenceData $evidence): bool => $evidence->included && $evidence->type === 'file' && ! $evidence->isImage(),
        ));
    }

    /** @return list<ReportEvidenceData> */
    public function includedUrlEvidence(): array
    {
        return array_values(array_filter(
            $this->evidences,
            static fn (ReportEvidenceData $evidence): bool => $evidence->included && $evidence->type === 'url',
        ));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'include_in_report' => $this->includeInReport,
            'title' => $this->title,
            'category' => $this->category,
            'scope_type' => $this->scopeType,
            'scope_label' => $this->scopeLabel,
            'scope_description' => $this->scopeDescription,
            'sites' => $this->sites,
            'assets' => array_map(static fn (ReportAssetData $asset): array => $asset->toArray(), $this->assets),
            'consequence_label' => $this->consequenceLabel,
            'consequence_color' => $this->consequenceColor,
            'likelihood_label' => $this->likelihoodLabel,
            'likelihood_color' => $this->likelihoodColor,
            'priority_label' => $this->priorityLabel,
            'priority_color' => $this->priorityColor,
            'priority_overridden' => $this->priorityOverridden,
            'priority_rationale' => $this->priorityRationale,
            'problem' => $this->problem,
            'entrepreneur_notes' => $this->entrepreneurNotes,
            'technical_notes' => $this->technicalNotes,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'solutions' => array_map(static fn (ReportSolutionData $solution): array => $solution->toArray(), $this->solutions),
            'evidences' => array_map(static fn (ReportEvidenceData $evidence): array => $evidence->toArray(), $this->evidences),
            'resolution_notes' => $this->resolutionNotes,
            'resolved_at' => $this->resolvedAt,
            'resolved_at_label' => $this->resolvedAtLabel,
            'page_plan' => $this->pagePlan->toArray(),
            'risk_matrix' => $this->riskMatrix?->toArray(),
        ];
    }
}
