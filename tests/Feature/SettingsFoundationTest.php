<?php

declare(strict_types=1);

use App\Enums\CoverTitleMode;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\ReportSettingsPage;
use App\Models\RiskProfile;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('resolves report settings from the real migrated settings state', function (): void {
    expect(DB::table('migrations')
        ->where('migration', '2026_07_18_000015_add_report_presentation_settings')
        ->exists())->toBeTrue()
        ->and(DB::table('migrations')
            ->where('migration', '2026_07_22_000020_remove_report_confidentiality_label')
            ->exists())->toBeTrue()
        ->and(DB::table('migrations')
            ->where('migration', '2026_07_24_000021_promote_weasyprint_report_settings')
            ->exists())->toBeTrue()
        ->and(DB::table('settings')
            ->where('group', 'report')
            ->where('name', 'confidentiality_label')
            ->exists())->toBeFalse()
        ->and(DB::table('settings')
            ->where('group', 'report')
            ->whereIn('name', ['repeated_header_footer', 'header_text', 'footer_text', 'new_page_per_finding'])
            ->exists())->toBeFalse();

    $settings = app(ReportSettings::class);

    expect($settings->cover_title_mode)->toBe(CoverTitleMode::Separate)
        ->and($settings->show_priority_descriptions)->toBeTrue()
        ->and($settings->show_resolution)->toBeTrue()
        ->and($settings->toArray())->not->toHaveKey('confidentiality_label');

    $settings->business_name = 'Consulenza Migrazione S.r.l.';
    $settings->primary_color = '#1A2B3C';
    $settings->save()->refresh();

    expect($settings->business_name)->toBe('Consulenza Migrazione S.r.l.')
        ->and($settings->primary_color)->toBe('#1A2B3C')
        ->and($settings->cover_title_mode)->toBe(CoverTitleMode::Separate)
        ->and($settings->show_priority_descriptions)->toBeTrue()
        ->and($settings->toArray())->not->toHaveKey('confidentiality_label');
});

it('mounts the report settings Filament page from migrated values', function (): void {
    $this->actingAs(User::factory()->create());

    $page = Livewire::test(ReportSettingsPage::class)
        ->assertSuccessful()
        ->assertSee(__('assestme.settings.report.output'))
        ->assertDontSee(__('assestme.settings.fields.summary_solutions'))
        ->assertDontSee(__('assestme.settings.fields.technical_notes_in_report'))
        ->assertDontSee(__('assestme.settings.fields.costs_in_report'))
        ->assertDontSee(__('assestme.settings.fields.new_page_per_finding'))
        ->assertDontSee('assestme.settings.fields.new_page_per_finding')
        ->assertFormSet([
            'cover_title_mode' => CoverTitleMode::Separate->value,
            'show_priority_descriptions' => true,
            'show_resolution' => true,
            'currency' => 'EUR',
        ]);

    expect($page->instance()->getMaxContentWidth())->toBe(Width::Full);
});

it('describes every visible report option and hides obsolete duplicate controls', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(ReportSettingsPage::class)->assertSuccessful();

    $describedFields = [
        'consultant_name',
        'business_name',
        'consultant_role',
        'consultant_email',
        'consultant_phone',
        'consultant_website',
        'consultant_address',
        'consultant_vat_number',
        'consultant_pec',
        'consultant_tax_code',
        'consultant_logo',
        'signature_name',
        'signature_role',
        'default_title_pattern',
        'primary_color',
        'branding',
        'cover_title_mode',
        'show_priority_descriptions',
        'cover',
        'content_index',
        'executive_summary',
        'risk_legend',
        'summary_table',
        'methodology',
        'page_numbers',
        'show_resolution',
        'signature_block',
        'disclaimer',
        'technical_notes',
        'alternative_solutions',
        'costs',
        'evidence',
        'evidence_captions',
        'freeze_after_generation',
        'currency',
        'currency_symbol',
        'currency_symbol_position',
        'currency_decimals',
        'report_excluded_findings_in_xlsx',
        'methodology_text',
        'disclaimer_text',
        'signature_text',
    ];
    foreach ($describedFields as $field) {
        $page->assertSee(__("assestme.settings.help.{$field}"));
    }

    $page
        ->assertDontSee(__('assestme.settings.fields.evidence_included_by_default'))
        ->assertDontSee(__('assestme.settings.fields.captions_visible_by_default'))
        ->assertDontSee(__('assestme.settings.fields.summary_solutions'))
        ->assertDontSee(__('assestme.settings.fields.technical_notes_in_report'))
        ->assertDontSee(__('assestme.settings.fields.costs_in_report'))
        ->assertDontSee(__('assestme.settings.fields.new_page_per_finding'));
});

it('persists validated general settings with the seeded active risk profile', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());
    $profile = RiskProfile::query()->where('is_default', true)->firstOrFail();

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm([
            'application_name' => 'AssestMe Test',
            'active_risk_profile_id' => $profile->getKey(),
            'max_evidence_file_mb' => 20,
            'max_assessment_evidence_mb' => 300,
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertDontSee(__('assestme.settings.report.output'))
        ->assertDontSee(__('assestme.settings.fields.new_page_per_finding'));

    $settings = app(GeneralSettings::class);
    expect($settings->application_name)->toBe('AssestMe Test')
        ->and($settings->active_risk_profile_id)->toBe($profile->getKey())
        ->and($settings->max_evidence_file_mb)->toBe(20)
        ->and($settings->max_assessment_evidence_mb)->toBe(300);
});

it('shows the compact evidence file unit and the reduced default limits', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(GeneralSettingsPage::class)
        ->assertSuccessful()
        ->assertSee('Dimensione Massima File')
        ->assertSee('Dimensione Massima Assessment')
        ->assertSee('Mb')
        ->assertFormSet([
            'max_evidence_file_mb' => 5,
            'max_assessment_evidence_mb' => 100,
        ]);
});

it('rejects an assessment evidence limit below the single file limit', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm(['max_evidence_file_mb' => 25, 'max_assessment_evidence_mb' => 10])
        ->call('save')
        ->assertHasFormErrors(['max_assessment_evidence_mb']);

    expect(app(GeneralSettings::class)->max_assessment_evidence_mb)->toBe(100);
});

it('normalizes report identity values without introducing VAT calculations', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ReportSettingsPage::class)
        ->fillForm([
            'consultant_name' => 'Mario Rossi',
            'consultant_vat_number' => 'it 01234567890',
            'consultant_tax_code' => 'rss mra 80a01 h501u',
            'primary_color' => '#a1b2c3',
            'cover_title_mode' => CoverTitleMode::Combined->value,
            'show_priority_descriptions' => false,
            'currency' => 'USD',
            'currency_symbol' => '$',
            'currency_symbol_position' => 'before',
            'currency_decimals' => 0,
            'report_excluded_findings_in_xlsx' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ReportSettings::class);
    $generalSettings = app(GeneralSettings::class);
    expect($settings->consultant_vat_number)->toBe('IT01234567890')
        ->and($settings->consultant_tax_code)->toBe('RSSMRA80A01H501U')
        ->and($settings->primary_color)->toBe('#A1B2C3')
        ->and($settings->cover_title_mode)->toBe(CoverTitleMode::Combined)
        ->and($settings->show_priority_descriptions)->toBeFalse()
        ->and($settings->toArray())->not->toHaveKeys(['vat_rate', 'taxable_amount', 'tax_amount'])
        ->and($generalSettings->currency)->toBe('USD')
        ->and($generalSettings->currency_symbol)->toBe('$')
        ->and($generalSettings->currency_symbol_position)->toBe('before')
        ->and($generalSettings->currency_decimals)->toBe(0)
        ->and($generalSettings->report_excluded_findings_in_xlsx)->toBeTrue();

    Livewire::test(ReportSettingsPage::class)
        ->fillForm([
            'cover_title_mode' => CoverTitleMode::Separate->value,
            'show_priority_descriptions' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ReportSettings::class);
    expect($settings->cover_title_mode)->toBe(CoverTitleMode::Separate)
        ->and($settings->show_priority_descriptions)->toBeTrue();
});

it('requires authentication for both settings pages', function (): void {
    $this->get(GeneralSettingsPage::getUrl())->assertRedirect('/admin/login');
    $this->get(ReportSettingsPage::getUrl())->assertRedirect('/admin/login');
});
