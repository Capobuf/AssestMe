<?php

declare(strict_types=1);

use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\ReportSettingsPage;
use App\Models\RiskProfile;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Livewire\Livewire;

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
            'currency' => 'EUR',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(GeneralSettings::class);
    expect($settings->application_name)->toBe('AssestMe Test')
        ->and($settings->active_risk_profile_id)->toBe($profile->getKey())
        ->and($settings->max_evidence_file_mb)->toBe(20)
        ->and($settings->max_assessment_evidence_mb)->toBe(300);
});

it('rejects an assessment evidence limit below the single file limit', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());

    Livewire::test(GeneralSettingsPage::class)
        ->fillForm(['max_evidence_file_mb' => 25, 'max_assessment_evidence_mb' => 10])
        ->call('save')
        ->assertHasFormErrors(['max_assessment_evidence_mb']);

    expect(app(GeneralSettings::class)->max_assessment_evidence_mb)->toBe(250);
});

it('normalizes report identity values without introducing VAT calculations', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ReportSettingsPage::class)
        ->fillForm([
            'consultant_name' => 'Mario Rossi',
            'consultant_vat_number' => 'it 01234567890',
            'consultant_tax_code' => 'rss mra 80a01 h501u',
            'primary_color' => '#a1b2c3',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(ReportSettings::class);
    expect($settings->consultant_vat_number)->toBe('IT01234567890')
        ->and($settings->consultant_tax_code)->toBe('RSSMRA80A01H501U')
        ->and($settings->primary_color)->toBe('#A1B2C3')
        ->and($settings->toArray())->not->toHaveKeys(['vat_rate', 'taxable_amount', 'tax_amount']);
});

it('requires authentication for both settings pages', function (): void {
    $this->get('/admin/general-settings-page')->assertRedirect('/admin/login');
    $this->get('/admin/report-settings-page')->assertRedirect('/admin/login');
});
