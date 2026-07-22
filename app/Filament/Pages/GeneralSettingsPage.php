<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\SettingsCluster;
use App\Models\RiskProfile;
use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

final class GeneralSettingsPage extends SettingsPage
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('assestme.settings.general.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.settings.general.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.settings.general.application'))
                ->schema([
                    TextInput::make('application_name')->label(__('assestme.settings.fields.application_name'))->required()->maxLength(120),
                    TextInput::make('timezone')->label(__('assestme.settings.fields.timezone'))->disabled()->dehydrated(),
                    TextInput::make('locale')->label(__('assestme.settings.fields.locale'))->disabled()->dehydrated(),
                    Select::make('active_risk_profile_id')
                        ->label(__('assestme.settings.fields.active_risk_profile'))
                        ->options(fn (): array => RiskProfile::query()->where('is_enabled', true)->orderBy('label')->pluck('label', 'id')->all())
                        ->required()
                        ->searchable(),
                    Select::make('deletion_policy')
                        ->label(__('assestme.settings.fields.deletion_policy'))
                        ->options(['archive' => __('assestme.settings.values.archive'), 'permanent' => __('assestme.settings.values.permanent')])
                        ->required(),
                    Toggle::make('autosave')->label(__('assestme.settings.fields.autosave'))->disabled()->dehydrated(),
                    Toggle::make('dark_mode')->label(__('assestme.settings.fields.dark_mode')),
                ])->columns(2),
            Section::make(__('assestme.settings.general.evidence'))
                ->schema([
                    TextInput::make('max_evidence_file_mb')->label(__('assestme.settings.fields.max_evidence_file_mb'))->numeric()->minValue(1)->maxValue(25)->required(),
                    TextInput::make('max_assessment_evidence_mb')->label(__('assestme.settings.fields.max_assessment_evidence_mb'))->numeric()->minValue(1)->maxValue(1000)->required(),
                    Toggle::make('evidence_included_by_default')->label(__('assestme.settings.fields.evidence_included_by_default')),
                    Toggle::make('captions_visible_by_default')->label(__('assestme.settings.fields.captions_visible_by_default')),
                ])->columns(2),
            Section::make(__('assestme.settings.general.report'))
                ->schema([
                    TextInput::make('currency')->label(__('assestme.settings.fields.currency'))->required()->length(3)->regex('/^[A-Z]{3}$/'),
                    TextInput::make('currency_symbol')->label(__('assestme.settings.fields.currency_symbol'))->required()->maxLength(8),
                    Select::make('currency_symbol_position')->label(__('assestme.settings.fields.currency_symbol_position'))->options(['before' => __('assestme.settings.values.before'), 'after' => __('assestme.settings.values.after')])->required(),
                    Select::make('currency_decimals')->label(__('assestme.settings.fields.currency_decimals'))->options([0 => '0', 2 => '2'])->required(),
                    Select::make('summary_solutions')->label(__('assestme.settings.fields.summary_solutions'))->options(['recommended_only' => __('assestme.settings.values.recommended_only'), 'all' => __('assestme.settings.values.all')])->required(),
                    Select::make('default_assessment_status')->label(__('assestme.settings.fields.default_assessment_status'))->options(['draft' => __('assestme.assessments.status.draft')])->disabled()->dehydrated(),
                    Toggle::make('technical_notes_in_report')->label(__('assestme.settings.fields.technical_notes_in_report')),
                    Toggle::make('costs_in_report')->label(__('assestme.settings.fields.costs_in_report')),
                    Toggle::make('new_page_per_finding')->label(__('assestme.settings.fields.new_page_per_finding')),
                    Toggle::make('report_excluded_findings_in_xlsx')->label(__('assestme.settings.fields.report_excluded_findings_in_xlsx')),
                ])->columns(2),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ((int) $data['max_assessment_evidence_mb'] < (int) $data['max_evidence_file_mb']) {
            throw ValidationException::withMessages([
                'data.max_assessment_evidence_mb' => __('assestme.settings.errors.assessment_limit'),
            ]);
        }

        $data['currency'] = mb_strtoupper((string) $data['currency']);

        return $data;
    }
}
