<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\CoverTitleMode;
use Spatie\LaravelSettings\Settings;

final class ReportSettings extends Settings
{
    public string $default_title_pattern;

    public ?string $consultant_name;

    public ?string $business_name;

    public ?string $consultant_role;

    public ?string $consultant_email;

    public ?string $consultant_phone;

    public ?string $consultant_website;

    public ?string $consultant_address;

    public ?string $consultant_vat_number;

    public ?string $consultant_pec;

    public ?string $consultant_tax_code;

    public ?string $consultant_logo_path;

    public ?string $signature_name;

    public ?string $signature_role;

    public string $primary_color;

    public string $branding;

    public CoverTitleMode $cover_title_mode;

    public bool $show_priority_descriptions;

    public bool $cover;

    public bool $content_index;

    public bool $executive_summary;

    public bool $risk_legend;

    public bool $summary_table;

    public bool $methodology;

    public bool $page_numbers;

    public bool $show_resolution;

    public bool $signature_block;

    public bool $disclaimer;

    public bool $technical_notes;

    public bool $alternative_solutions;

    public bool $costs;

    public bool $evidence;

    public bool $evidence_captions;

    public bool $freeze_after_generation;

    public ?string $methodology_text;

    public ?string $disclaimer_text;

    public ?string $signature_text;

    public static function group(): string
    {
        return 'report';
    }
}
