<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CoverTitleMode;
use App\Settings\ReportSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ReportSettingsPage extends SettingsPage
{
    protected static string $settings = ReportSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 31;

    public static function getNavigationLabel(): string
    {
        return __('assestme.settings.report.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.settings.report.title');
    }

    public static function getNavigationGroup(): string
    {
        return __('assestme.navigation.configuration');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.settings.report.identity'))
                ->schema([
                    TextInput::make('consultant_name')->label(__('assestme.settings.fields.consultant_name'))->maxLength(255),
                    TextInput::make('business_name')->label(__('assestme.settings.fields.business_name'))->maxLength(255),
                    TextInput::make('consultant_role')->label(__('assestme.settings.fields.consultant_role'))->maxLength(255),
                    TextInput::make('consultant_email')->label(__('assestme.settings.fields.consultant_email'))->email()->maxLength(254),
                    TextInput::make('consultant_phone')->label(__('assestme.settings.fields.consultant_phone'))->maxLength(40),
                    TextInput::make('consultant_website')->label(__('assestme.settings.fields.consultant_website'))->url()->regex('/^https?:\/\//i')->maxLength(2048),
                    Textarea::make('consultant_address')->label(__('assestme.settings.fields.consultant_address'))->rows(3)->maxLength(20000),
                    TextInput::make('consultant_vat_number')->label(__('assestme.settings.fields.consultant_vat_number'))->maxLength(32),
                    TextInput::make('consultant_pec')->label(__('assestme.settings.fields.consultant_pec'))->email()->maxLength(254),
                    TextInput::make('consultant_tax_code')->label(__('assestme.settings.fields.consultant_tax_code'))->maxLength(32),
                    FileUpload::make('consultant_logo_path')
                        ->label(__('assestme.settings.fields.consultant_logo'))
                        ->disk('local')
                        ->directory('branding')
                        ->visibility('private')
                        ->acceptedFileTypes(['image/png', 'image/jpeg'])
                        ->maxSize(5120)
                        ->preventFilePathTampering(),
                    TextInput::make('signature_name')->label(__('assestme.settings.fields.signature_name'))->maxLength(255),
                    TextInput::make('signature_role')->label(__('assestme.settings.fields.signature_role'))->maxLength(255),
                ])->columns(2),
            Section::make(__('assestme.settings.report.layout'))
                ->schema([
                    TextInput::make('default_title_pattern')->label(__('assestme.settings.fields.default_title_pattern'))->required()->maxLength(255),
                    ColorPicker::make('primary_color')->label(__('assestme.settings.fields.primary_color'))->required()->regex('/^#[0-9A-Fa-f]{6}$/'),
                    Select::make('branding')->label(__('assestme.settings.fields.branding'))->options([
                        'consultant' => __('assestme.settings.values.consultant'),
                        'client' => __('assestme.settings.values.client'),
                        'both' => __('assestme.settings.values.both'),
                    ])->required(),
                    Select::make('cover_title_mode')
                        ->label(__('assestme.settings.fields.cover_title_mode'))
                        ->options(CoverTitleMode::options())
                        ->required(),
                    Toggle::make('show_priority_descriptions')
                        ->label(__('assestme.settings.fields.show_priority_descriptions')),
                    TextInput::make('confidentiality_label')->label(__('assestme.settings.fields.confidentiality_label'))->required()->maxLength(40),
                    TextInput::make('header_text')->label(__('assestme.settings.fields.header_text'))->maxLength(120),
                    TextInput::make('footer_text')->label(__('assestme.settings.fields.footer_text'))->maxLength(120),
                    ...self::toggleFields(),
                ])->columns(2),
            Section::make(__('assestme.settings.report.texts'))
                ->schema([
                    Textarea::make('methodology_text')->label(__('assestme.settings.fields.methodology_text'))->rows(5)->maxLength(20000),
                    Textarea::make('disclaimer_text')->label(__('assestme.settings.fields.disclaimer_text'))->rows(5)->maxLength(20000),
                    Textarea::make('signature_text')->label(__('assestme.settings.fields.signature_text'))->rows(3)->maxLength(20000),
                ]),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $mode = $data['cover_title_mode'] ?? CoverTitleMode::Separate;
        $data['cover_title_mode'] = $mode instanceof CoverTitleMode ? $mode->value : (string) $mode;

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['consultant_vat_number', 'consultant_tax_code'] as $field) {
            $value = $data[$field] ?? null;
            $data[$field] = is_string($value) && $value !== '' ? mb_strtoupper(str_replace(' ', '', $value)) : null;
        }

        $data['primary_color'] = mb_strtoupper((string) $data['primary_color']);
        $data['cover_title_mode'] = CoverTitleMode::from((string) $data['cover_title_mode']);

        return $data;
    }

    /** @return list<Toggle> */
    private static function toggleFields(): array
    {
        $fields = [
            'cover', 'content_index', 'executive_summary', 'risk_legend', 'summary_table', 'methodology',
            'repeated_header_footer', 'page_numbers', 'signature_block', 'disclaimer', 'technical_notes',
            'alternative_solutions', 'costs', 'evidence', 'evidence_captions', 'new_page_per_finding',
            'freeze_after_generation',
        ];

        return array_map(
            static fn (string $field): Toggle => Toggle::make($field)->label(__("assestme.settings.fields.{$field}")),
            $fields,
        );
    }
}
