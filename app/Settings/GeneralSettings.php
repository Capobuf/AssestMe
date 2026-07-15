<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings
{
    public string $application_name;

    public string $timezone;

    public string $locale;

    public string $currency;

    public string $currency_symbol;

    public string $currency_symbol_position;

    public int $currency_decimals;

    public string $deletion_policy;

    public int $max_evidence_file_mb;

    public int $max_assessment_evidence_mb;

    public bool $evidence_included_by_default;

    public bool $captions_visible_by_default;

    public bool $technical_notes_in_report;

    public bool $costs_in_report;

    public string $summary_solutions;

    public ?int $active_risk_profile_id;

    public bool $new_page_per_finding;

    public string $default_assessment_status;

    public bool $autosave;

    public bool $dark_mode;

    public bool $report_excluded_findings_in_xlsx;

    public static function group(): string
    {
        return 'general';
    }
}
