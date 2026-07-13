<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.application_name', 'AssestMe');
        $this->migrator->add('general.timezone', 'Europe/Rome');
        $this->migrator->add('general.locale', 'it');
        $this->migrator->add('general.currency', 'EUR');
        $this->migrator->add('general.currency_symbol', '€');
        $this->migrator->add('general.currency_symbol_position', 'after');
        $this->migrator->add('general.currency_decimals', 2);
        $this->migrator->add('general.deletion_policy', 'archive');
        $this->migrator->add('general.max_evidence_file_mb', 25);
        $this->migrator->add('general.max_assessment_evidence_mb', 250);
        $this->migrator->add('general.evidence_included_by_default', true);
        $this->migrator->add('general.captions_visible_by_default', true);
        $this->migrator->add('general.technical_notes_in_report', false);
        $this->migrator->add('general.costs_in_report', true);
        $this->migrator->add('general.summary_solutions', 'recommended_only');
        $this->migrator->add('general.active_risk_profile_id', null);
        $this->migrator->add('general.new_page_per_finding', true);
        $this->migrator->add('general.default_assessment_status', 'draft');
        $this->migrator->add('general.autosave', true);
        $this->migrator->add('general.dark_mode', true);
        $this->migrator->add('general.report_excluded_findings_in_xlsx', false);

        $this->migrator->add('report.default_title_pattern', 'Assessment IT — {client}');
        $this->migrator->add('report.consultant_name', null);
        $this->migrator->add('report.business_name', null);
        $this->migrator->add('report.consultant_role', null);
        $this->migrator->add('report.consultant_email', null);
        $this->migrator->add('report.consultant_phone', null);
        $this->migrator->add('report.consultant_website', null);
        $this->migrator->add('report.consultant_address', null);
        $this->migrator->add('report.consultant_vat_number', null);
        $this->migrator->add('report.consultant_pec', null);
        $this->migrator->add('report.consultant_tax_code', null);
        $this->migrator->add('report.consultant_logo_path', null);
        $this->migrator->add('report.signature_name', null);
        $this->migrator->add('report.signature_role', null);
        $this->migrator->add('report.primary_color', '#2563EB');
        $this->migrator->add('report.branding', 'consultant');
        $this->migrator->add('report.cover', true);
        $this->migrator->add('report.content_index', false);
        $this->migrator->add('report.executive_summary', true);
        $this->migrator->add('report.risk_legend', true);
        $this->migrator->add('report.summary_table', true);
        $this->migrator->add('report.methodology', true);
        $this->migrator->add('report.repeated_header_footer', true);
        $this->migrator->add('report.page_numbers', true);
        $this->migrator->add('report.signature_block', false);
        $this->migrator->add('report.disclaimer', true);
        $this->migrator->add('report.confidentiality_label', 'Riservato');
        $this->migrator->add('report.technical_notes', false);
        $this->migrator->add('report.alternative_solutions', true);
        $this->migrator->add('report.costs', true);
        $this->migrator->add('report.evidence', true);
        $this->migrator->add('report.evidence_captions', true);
        $this->migrator->add('report.new_page_per_finding', true);
        $this->migrator->add('report.freeze_after_generation', false);
        $this->migrator->add('report.methodology_text', null);
        $this->migrator->add('report.disclaimer_text', null);
        $this->migrator->add('report.header_text', null);
        $this->migrator->add('report.footer_text', null);
        $this->migrator->add('report.signature_text', null);
    }
};
