<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->addIfMissing('general.application_name', 'AssestMe');
        $this->addIfMissing('general.timezone', 'Europe/Rome');
        $this->addIfMissing('general.locale', 'it');
        $this->addIfMissing('general.currency', 'EUR');
        $this->addIfMissing('general.currency_symbol', '€');
        $this->addIfMissing('general.currency_symbol_position', 'after');
        $this->addIfMissing('general.currency_decimals', 2);
        $this->addIfMissing('general.deletion_policy', 'archive');
        $this->addIfMissing('general.max_evidence_file_mb', 25);
        $this->addIfMissing('general.max_assessment_evidence_mb', 250);
        $this->addIfMissing('general.evidence_included_by_default', true);
        $this->addIfMissing('general.captions_visible_by_default', true);
        $this->addIfMissing('general.technical_notes_in_report', false);
        $this->addIfMissing('general.costs_in_report', true);
        $this->addIfMissing('general.summary_solutions', 'recommended_only');
        $this->addIfMissing('general.active_risk_profile_id', null);
        $this->addIfMissing('general.new_page_per_finding', true);
        $this->addIfMissing('general.default_assessment_status', 'draft');
        $this->addIfMissing('general.autosave', true);
        $this->addIfMissing('general.dark_mode', true);
        $this->addIfMissing('general.report_excluded_findings_in_xlsx', false);

        $this->addIfMissing('report.default_title_pattern', 'Assessment IT — {client}');
        $this->addIfMissing('report.consultant_name', null);
        $this->addIfMissing('report.business_name', null);
        $this->addIfMissing('report.consultant_role', null);
        $this->addIfMissing('report.consultant_email', null);
        $this->addIfMissing('report.consultant_phone', null);
        $this->addIfMissing('report.consultant_website', null);
        $this->addIfMissing('report.consultant_address', null);
        $this->addIfMissing('report.consultant_vat_number', null);
        $this->addIfMissing('report.consultant_pec', null);
        $this->addIfMissing('report.consultant_tax_code', null);
        $this->addIfMissing('report.consultant_logo_path', null);
        $this->addIfMissing('report.signature_name', null);
        $this->addIfMissing('report.signature_role', null);
        $this->addIfMissing('report.primary_color', '#2563EB');
        $this->addIfMissing('report.branding', 'consultant');
        $this->addIfMissing('report.cover', true);
        $this->addIfMissing('report.content_index', false);
        $this->addIfMissing('report.executive_summary', true);
        $this->addIfMissing('report.risk_legend', true);
        $this->addIfMissing('report.summary_table', true);
        $this->addIfMissing('report.methodology', true);
        $this->addIfMissing('report.repeated_header_footer', true);
        $this->addIfMissing('report.page_numbers', true);
        $this->addIfMissing('report.signature_block', false);
        $this->addIfMissing('report.disclaimer', true);
        $this->addIfMissing('report.confidentiality_label', 'Riservato');
        $this->addIfMissing('report.technical_notes', false);
        $this->addIfMissing('report.alternative_solutions', true);
        $this->addIfMissing('report.costs', true);
        $this->addIfMissing('report.evidence', true);
        $this->addIfMissing('report.evidence_captions', true);
        $this->addIfMissing('report.new_page_per_finding', true);
        $this->addIfMissing('report.freeze_after_generation', false);
        $this->addIfMissing('report.methodology_text', null);
        $this->addIfMissing('report.disclaimer_text', null);
        $this->addIfMissing('report.header_text', null);
        $this->addIfMissing('report.footer_text', null);
        $this->addIfMissing('report.signature_text', null);
    }

    private function addIfMissing(string $property, bool|int|string|null $value): void
    {
        if (! $this->migrator->exists($property)) {
            $this->migrator->add($property, $value);
        }
    }
};
