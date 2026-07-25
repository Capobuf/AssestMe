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
                    Select::make('default_assessment_status')
                        ->label(__('assestme.settings.fields.default_assessment_status'))
                        ->options(['draft' => __('assestme.assessments.status.draft')])
                        ->disabled()
                        ->dehydrated(),
                    Toggle::make('autosave')->label(__('assestme.settings.fields.autosave'))->disabled()->dehydrated(),
                    Toggle::make('dark_mode')->label(__('assestme.settings.fields.dark_mode')),
                ])->columns(2),
            Section::make(__('assestme.settings.general.evidence'))
                ->schema([
                    TextInput::make('max_evidence_file_mb')->label(__('assestme.settings.fields.max_evidence_file_mb'))->numeric()->minValue(1)->maxValue(25)->required(),
                    TextInput::make('max_assessment_evidence_mb')->label(__('assestme.settings.fields.max_assessment_evidence_mb'))->numeric()->minValue(1)->maxValue(1000)->required(),
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

        return $data;
    }
}
