<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Assessments\ValidateAssessmentCompletion;
use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\ReportAssetData;
use App\Data\Reports\ReportEvidenceData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportLogoData;
use App\Data\Reports\ReportPriorityData;
use App\Data\Reports\ReportSolutionData;
use App\Enums\CoverTitleMode;
use App\Enums\EvidenceType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\PriorityLevel;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class BuildAssessmentSnapshot
{
    public function __construct(
        private readonly ValidateAssessmentCompletion $validateCompletion,
        private readonly FormatEstimate $formatEstimate,
        private readonly GeneralSettings $generalSettings,
        private readonly ReportSettings $reportSettings,
    ) {}

    public function __invoke(
        Assessment $assessment,
        ?Carbon $generatedAt = null,
        bool $includeExcludedFindings = false,
    ): AssessmentReportData {
        ($this->validateCompletion)($assessment);
        $assessment->load($this->relations());
        $generatedAt ??= now();

        $findings = $assessment->findings
            ->when(
                ! $includeExcludedFindings,
                static fn (Collection $findings): Collection => $findings->where('include_in_report', true),
            )
            ->values()
            ->map(fn (Finding $finding, int $index): ReportFindingData => $this->finding($finding, $index + 1))
            ->all();

        $client = $assessment->client;
        $clientAddress = $this->address([
            $client->address,
            trim(implode(' ', array_filter([$client->postal_code, $client->city]))),
            $client->province,
            $client->country,
        ]);
        $clientName = $client->displayName();

        return new AssessmentReportData(
            generatedAt: $generatedAt->utc()->toIso8601String(),
            applicationVersion: (string) config('assestme.version'),
            locale: $assessment->locale,
            title: $this->reportTitle($assessment, $clientName),
            assessmentId: (int) $assessment->getKey(),
            assessmentTitle: $assessment->title,
            assessmentDate: $assessment->assessment_date->format('Y-m-d'),
            assessmentDateLabel: $assessment->assessment_date->format('d/m/Y'),
            assessmentStatus: $assessment->status->value,
            assessmentStatusLabel: $assessment->status::options()[$assessment->status->value],
            assessmentScope: $this->scopeLabel($assessment->scope_type, $assessment->scope_description),
            scopeDescription: $assessment->scope_description,
            assessmentSites: $assessment->sites->pluck('name')->values()->all(),
            introduction: $assessment->introduction,
            executiveSummary: $assessment->executive_summary,
            methodologyNotes: $assessment->methodology_notes,
            clientId: (int) $client->getKey(),
            clientName: $clientName,
            clientLegalName: $client->legal_name,
            clientVatNumber: $client->vat_number,
            clientTaxCode: $client->tax_code,
            clientEmail: $client->email,
            clientPhone: $client->phone,
            clientWebsite: $client->website,
            clientAddress: $clientAddress,
            logos: $this->logos($client->logo_path),
            priorityLegend: $this->priorityLegend(),
            findings: $findings,
            settingsSnapshot: $this->settingsSnapshot(),
        );
    }

    /** @return array<int|string, callable(Relation<Model, Model, Model|Collection<int, Model>|null>): void|string> */
    private function relations(): array
    {
        // Referenced archived master data must remain visible in current reports and immutable snapshots.
        $withArchived = static function (Relation $relation): void {
            $relation->getQuery()->withoutGlobalScope(SoftDeletingScope::class);
        };

        return [
            'client' => $withArchived,
            'sites' => static function (Relation $relation): void {
                $relation->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->orderBy('name');
            },
            'findings' => static function (Relation $relation): void {
                $relation->getQuery()->orderBy('sort_order');
            },
            'findings.category' => $withArchived,
            'findings.consequenceLevel',
            'findings.likelihoodLevel',
            'findings.priorityLevel',
            'findings.solutions.effortLevel',
            'findings.sites' => static function (Relation $relation): void {
                $relation->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->orderBy('name');
            },
            'findings.assets' => static function (Relation $relation): void {
                $relation->getQuery()->withoutGlobalScope(SoftDeletingScope::class)->orderBy('id');
            },
            'findings.assets.assetType',
            'findings.assets.site' => $withArchived,
            'findings.evidences',
        ];
    }

    private function finding(Finding $finding, int $number): ReportFindingData
    {
        $solutions = $finding->solutions
            ->map(fn (FindingSolution $solution): ReportSolutionData => $this->solution($finding, $solution))
            ->values()
            ->all();
        $assets = $finding->assets
            ->map(fn (Asset $asset): ReportAssetData => $this->asset($asset))
            ->values()
            ->all();
        $evidences = $finding->evidences
            ->map(fn (Evidence $evidence): ReportEvidenceData => $this->evidence($finding, $evidence))
            ->values()
            ->all();

        return new ReportFindingData(
            id: (int) $finding->getKey(),
            number: $number,
            includeInReport: $finding->include_in_report,
            title: (string) $finding->title,
            category: (string) $finding->category?->name,
            scopeType: $finding->scope_type->value,
            scopeLabel: $this->scopeLabel($finding->scope_type, $finding->scope_description),
            scopeDescription: $finding->scope_description,
            sites: $finding->sites->pluck('name')->values()->all(),
            assets: $assets,
            consequenceLabel: $finding->consequenceLevel?->label,
            consequenceColor: $finding->consequenceLevel?->color,
            likelihoodLabel: $finding->likelihoodLevel?->label,
            likelihoodColor: $finding->likelihoodLevel?->color,
            priorityLabel: (string) $finding->priorityLevel?->label,
            priorityColor: (string) $finding->priorityLevel?->color,
            priorityOverridden: $finding->priority_is_overridden,
            priorityRationale: $finding->priority_rationale,
            problem: (string) $finding->problem,
            entrepreneurNotes: $finding->entrepreneur_notes,
            technicalNotes: $finding->technical_notes,
            status: $finding->status->value,
            statusLabel: $finding->status::options()[$finding->status->value],
            solutions: $solutions,
            evidences: $evidences,
            resolutionNotes: $finding->resolution_notes,
            resolvedAt: $finding->resolved_at?->utc()->toIso8601String(),
            resolvedAtLabel: $finding->resolved_at?->timezone($this->generalSettings->timezone)->format('d/m/Y H:i'),
        );
    }

    private function solution(Finding $finding, FindingSolution $solution): ReportSolutionData
    {
        return new ReportSolutionData(
            id: (int) $solution->getKey(),
            title: $solution->title,
            description: $solution->description,
            comparisonNotes: $solution->comparison_notes,
            effortLabel: $solution->effortLevel?->label,
            effortNotes: $solution->effort_notes,
            estimateType: $solution->estimate_type->value,
            amountMin: $solution->amount_min === null ? null : number_format((float) $solution->amount_min, 2, '.', ''),
            amountMax: $solution->amount_max === null ? null : number_format((float) $solution->amount_max, 2, '.', ''),
            currencyCode: $solution->currency_code,
            billingFrequency: $solution->billing_frequency->value,
            customBillingFrequency: $solution->custom_billing_frequency,
            estimateNotes: $solution->estimate_notes,
            estimateLabel: $this->formatEstimate->handle($solution),
            recommended: $finding->recommended_solution_id === $solution->getKey(),
            implemented: $finding->implemented_solution_id === $solution->getKey(),
            sortOrder: $solution->sort_order,
        );
    }

    private function asset(Asset $asset): ReportAssetData
    {
        $manufacturerModel = trim(implode(' ', array_filter([$asset->manufacturer, $asset->model])));
        $parts = array_filter([
            $asset->name,
            $asset->assetType->name,
            $asset->site?->name,
            $manufacturerModel === '' ? null : $manufacturerModel,
            $asset->hostname,
            $asset->ip_address,
        ]);

        return new ReportAssetData(
            id: (int) $asset->getKey(),
            name: $asset->name,
            type: $asset->assetType->name,
            site: $asset->site?->name,
            manufacturer: $asset->manufacturer,
            model: $asset->model,
            hostname: $asset->hostname,
            ipAddress: $asset->ip_address,
            displayLabel: implode(' — ', $parts),
        );
    }

    private function evidence(Finding $finding, Evidence $evidence): ReportEvidenceData
    {
        $imageDataUri = null;
        if ($evidence->type === EvidenceType::File && $evidence->include_in_report) {
            $contents = $this->verifiedEvidenceContents($finding, $evidence);
            if (in_array($evidence->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $imageDataUri = 'data:'.$evidence->mime_type.';base64,'.base64_encode($contents);
            }
        }

        return new ReportEvidenceData(
            id: (int) $evidence->getKey(),
            type: $evidence->type->value,
            title: $evidence->title,
            filePath: $evidence->file_path,
            url: $evidence->url,
            originalFilename: $evidence->original_filename,
            caption: $evidence->caption,
            mimeType: $evidence->mime_type,
            sizeBytes: $evidence->size_bytes,
            sha256: $evidence->sha256,
            included: $evidence->include_in_report,
            sortOrder: $evidence->sort_order,
            imageDataUri: $imageDataUri,
        );
    }

    private function verifiedEvidenceContents(Finding $finding, Evidence $evidence): string
    {
        $disk = Storage::disk('local');
        if ($evidence->file_path === null || ! $disk->exists($evidence->file_path)) {
            $this->invalidEvidence($finding, $evidence, __('assestme.reports.errors.evidence_missing'));
        }

        $contents = $disk->get((string) $evidence->file_path);
        $hash = hash('sha256', $contents);
        if ($evidence->sha256 === null || ! hash_equals($evidence->sha256, $hash)) {
            $this->invalidEvidence($finding, $evidence, __('assestme.reports.errors.evidence_corrupt'));
        }

        return $contents;
    }

    private function invalidEvidence(Finding $finding, Evidence $evidence, string $reason): never
    {
        throw ValidationException::withMessages([
            'report' => __('assestme.reports.errors.evidence', [
                'finding' => $finding->title,
                'evidence' => $evidence->title,
                'reason' => $reason,
            ]),
        ]);
    }

    /** @return list<ReportLogoData> */
    private function logos(?string $clientLogoPath): array
    {
        $paths = match ($this->reportSettings->branding) {
            'consultant' => ['consultant' => $this->reportSettings->consultant_logo_path],
            'client' => ['client' => $clientLogoPath],
            'both' => [
                'consultant' => $this->reportSettings->consultant_logo_path,
                'client' => $clientLogoPath,
            ],
            default => throw new \LogicException('The configured report branding mode is invalid.'),
        };

        $logos = [];
        foreach ($paths as $owner => $path) {
            if ($path === null) {
                continue;
            }

            $disk = Storage::disk('local');
            if (! $disk->exists($path)) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_missing')]);
            }

            $contents = $disk->get($path);
            if (strlen($contents) > 5 * 1024 * 1024) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_too_large')]);
            }

            $mimeType = $disk->mimeType($path);
            if (! is_string($mimeType) || ! in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_invalid')]);
            }

            $imageInfo = getimagesizefromstring($contents);
            if ($imageInfo === false || $imageInfo['mime'] !== $mimeType) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_invalid')]);
            }

            $logos[] = new ReportLogoData(
                owner: $owner,
                path: $path,
                mimeType: $mimeType,
                sha256: hash('sha256', $contents),
                dataUri: 'data:'.$mimeType.';base64,'.base64_encode($contents),
            );
        }

        return $logos;
    }

    /** @return list<ReportPriorityData> */
    private function priorityLegend(): array
    {
        if ($this->generalSettings->active_risk_profile_id === null) {
            return [];
        }

        return PriorityLevel::query()
            ->where('risk_profile_id', $this->generalSettings->active_risk_profile_id)
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (PriorityLevel $level): ReportPriorityData => new ReportPriorityData(
                code: $level->code,
                label: $level->label,
                description: $level->description,
                color: $level->color,
                sortOrder: $level->sort_order,
            ))
            ->all();
    }

    private function scopeLabel(ScopeType $scope, ?string $description): string
    {
        $label = __('assestme.scopes.'.$scope->value);

        return filled($description) ? $label.' — '.$description : $label;
    }

    /** @param list<string|null> $parts */
    private function address(array $parts): ?string
    {
        $populated = array_values(array_filter($parts, static fn (?string $part): bool => filled($part)));
        if (count($populated) === 1 && mb_strtoupper(trim((string) $populated[0])) === 'IT') {
            return null;
        }

        $address = implode(', ', $populated);

        return $address === '' ? null : $address;
    }

    private function reportTitle(Assessment $assessment, string $clientName): string
    {
        $hasOverride = filled($assessment->report_title_override);
        $configured = trim($hasOverride
            ? (string) $assessment->report_title_override
            : $this->reportSettings->default_title_pattern);
        $baseTitle = $hasOverride ? $configured : str_replace('{client}', '', $configured);
        $baseTitle = trim((string) preg_replace('/(?:\s*[—-]\s*)+$/u', '', $baseTitle));
        $baseTitle = $baseTitle === '' ? __('assestme.reports.document.default_title') : $baseTitle;

        if ($this->reportSettings->cover_title_mode === CoverTitleMode::Separate) {
            return $baseTitle;
        }

        if (str_contains(mb_strtolower($baseTitle), mb_strtolower($clientName))) {
            return $baseTitle;
        }

        return $baseTitle.' — '.$clientName;
    }

    /** @return array<string, bool|int|string|null> */
    private function settingsSnapshot(): array
    {
        return [
            'application_name' => $this->generalSettings->application_name,
            'timezone' => $this->generalSettings->timezone,
            'locale' => $this->generalSettings->locale,
            'currency' => $this->generalSettings->currency,
            'currency_symbol' => $this->generalSettings->currency_symbol,
            'currency_symbol_position' => $this->generalSettings->currency_symbol_position,
            'currency_decimals' => $this->generalSettings->currency_decimals,
            'deletion_policy' => $this->generalSettings->deletion_policy,
            'max_evidence_file_mb' => $this->generalSettings->max_evidence_file_mb,
            'max_assessment_evidence_mb' => $this->generalSettings->max_assessment_evidence_mb,
            'evidence_included_by_default' => $this->generalSettings->evidence_included_by_default,
            'captions_visible_by_default' => $this->generalSettings->captions_visible_by_default,
            'technical_notes_in_report' => $this->generalSettings->technical_notes_in_report,
            'costs_in_report' => $this->generalSettings->costs_in_report,
            'summary_solutions' => $this->generalSettings->summary_solutions,
            'active_risk_profile_id' => $this->generalSettings->active_risk_profile_id,
            'general_new_page_per_finding' => $this->generalSettings->new_page_per_finding,
            'default_assessment_status' => $this->generalSettings->default_assessment_status,
            'autosave' => $this->generalSettings->autosave,
            'dark_mode' => $this->generalSettings->dark_mode,
            'report_excluded_findings_in_xlsx' => $this->generalSettings->report_excluded_findings_in_xlsx,
            'default_title_pattern' => $this->reportSettings->default_title_pattern,
            'consultant_name' => $this->reportSettings->consultant_name,
            'business_name' => $this->reportSettings->business_name,
            'consultant_role' => $this->reportSettings->consultant_role,
            'consultant_email' => $this->reportSettings->consultant_email,
            'consultant_phone' => $this->reportSettings->consultant_phone,
            'consultant_website' => $this->reportSettings->consultant_website,
            'consultant_address' => $this->reportSettings->consultant_address,
            'consultant_vat_number' => $this->reportSettings->consultant_vat_number,
            'consultant_pec' => $this->reportSettings->consultant_pec,
            'consultant_tax_code' => $this->reportSettings->consultant_tax_code,
            'consultant_logo_path' => $this->reportSettings->consultant_logo_path,
            'signature_name' => $this->reportSettings->signature_name,
            'signature_role' => $this->reportSettings->signature_role,
            'primary_color' => $this->reportSettings->primary_color,
            'branding' => $this->reportSettings->branding,
            'cover_title_mode' => $this->reportSettings->cover_title_mode->value,
            'show_priority_descriptions' => $this->reportSettings->show_priority_descriptions,
            'cover' => $this->reportSettings->cover,
            'content_index' => $this->reportSettings->content_index,
            'executive_summary' => $this->reportSettings->executive_summary,
            'risk_legend' => $this->reportSettings->risk_legend,
            'summary_table' => $this->reportSettings->summary_table,
            'methodology' => $this->reportSettings->methodology,
            'repeated_header_footer' => $this->reportSettings->repeated_header_footer,
            'page_numbers' => $this->reportSettings->page_numbers,
            'signature_block' => $this->reportSettings->signature_block,
            'disclaimer' => $this->reportSettings->disclaimer,
            'technical_notes' => $this->reportSettings->technical_notes,
            'alternative_solutions' => $this->reportSettings->alternative_solutions,
            'costs' => $this->reportSettings->costs,
            'evidence' => $this->reportSettings->evidence,
            'evidence_captions' => $this->reportSettings->evidence_captions,
            'new_page_per_finding' => $this->reportSettings->new_page_per_finding,
            'freeze_after_generation' => $this->reportSettings->freeze_after_generation,
            'methodology_text' => $this->reportSettings->methodology_text,
            'disclaimer_text' => $this->reportSettings->disclaimer_text,
            'header_text' => $this->reportSettings->header_text,
            'footer_text' => $this->reportSettings->footer_text,
            'signature_text' => $this->reportSettings->signature_text,
        ];
    }
}
