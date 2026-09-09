<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CoverTitleMode;
use App\Filament\Clusters\SettingsCluster;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ReportSettingsPage extends SettingsPage
{
    /** @var list<string> */
    private const GENERAL_REPORT_FIELDS = [
        'currency',
        'currency_symbol',
        'currency_symbol_position',
        'currency_decimals',
        'report_excluded_findings_in_xlsx',
    ];

    /** @var list<string> */
    private const PREVIEW_FIELDS = [
        'default_title_pattern',
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
        'consultant_logo_path',
        'signature_name',
        'signature_role',
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
        'methodology_text',
        'disclaimer_text',
        'signature_text',
        'currency',
        'currency_symbol',
        'currency_symbol_position',
        'currency_decimals',
    ];

    protected static ?string $cluster = SettingsCluster::class;

    protected static string $settings = ReportSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.report-settings-page';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** @var array<string, bool|int|string> */
    private array $pendingGeneralReportSettings = [];

    public static function getNavigationLabel(): string
    {
        return __('assestme.settings.report.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.settings.report.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'xl' => 5,
            ])
            ->extraAttributes([
                'class' => 'assestme-report-settings-layout',
                'data-dusk' => 'report-settings-layout',
            ])
            ->components([
                Group::make([
                    Section::make(__('assestme.settings.report.identity'))
                        ->schema([
                            TextInput::make('consultant_name')
                                ->label(__('assestme.settings.fields.consultant_name'))
                                ->helperText(__('assestme.settings.help.consultant_name'))
                                ->live(debounce: 500)
                                ->maxLength(255),
                            TextInput::make('business_name')
                                ->label(__('assestme.settings.fields.business_name'))
                                ->helperText(__('assestme.settings.help.business_name'))
                                ->live(debounce: 500)
                                ->maxLength(255),
                            TextInput::make('consultant_role')->label(__('assestme.settings.fields.consultant_role'))->helperText(__('assestme.settings.help.consultant_role'))->live(debounce: 500)->maxLength(255),
                            TextInput::make('consultant_email')->label(__('assestme.settings.fields.consultant_email'))->helperText(__('assestme.settings.help.consultant_email'))->live(debounce: 500)->email()->maxLength(254),
                            TextInput::make('consultant_phone')->label(__('assestme.settings.fields.consultant_phone'))->helperText(__('assestme.settings.help.consultant_phone'))->live(debounce: 500)->maxLength(40),
                            TextInput::make('consultant_website')->label(__('assestme.settings.fields.consultant_website'))->helperText(__('assestme.settings.help.consultant_website'))->live(debounce: 500)->url()->regex('/^https?:\/\//i')->maxLength(2048),
                            Textarea::make('consultant_address')->label(__('assestme.settings.fields.consultant_address'))->helperText(__('assestme.settings.help.consultant_address'))->live(debounce: 500)->rows(3)->maxLength(20000),
                            TextInput::make('consultant_vat_number')->label(__('assestme.settings.fields.consultant_vat_number'))->helperText(__('assestme.settings.help.consultant_vat_number'))->live(debounce: 500)->maxLength(32),
                            TextInput::make('consultant_pec')->label(__('assestme.settings.fields.consultant_pec'))->helperText(__('assestme.settings.help.consultant_pec'))->live(debounce: 500)->email()->maxLength(254),
                            TextInput::make('consultant_tax_code')->label(__('assestme.settings.fields.consultant_tax_code'))->helperText(__('assestme.settings.help.consultant_tax_code'))->live(debounce: 500)->maxLength(32),
                            FileUpload::make('consultant_logo_path')
                                ->label(__('assestme.settings.fields.consultant_logo'))
                                ->helperText(__('assestme.settings.help.consultant_logo'))
                                ->live()
                                ->disk('local')
                                ->directory('branding')
                                ->visibility('private')
                                ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                                ->maxSize(5120)
                                ->preventFilePathTampering(),
                            TextInput::make('signature_name')->label(__('assestme.settings.fields.signature_name'))->helperText(__('assestme.settings.help.signature_name'))->live(debounce: 500)->maxLength(255),
                            TextInput::make('signature_role')->label(__('assestme.settings.fields.signature_role'))->helperText(__('assestme.settings.help.signature_role'))->live(debounce: 500)->maxLength(255),
                        ])
                        ->columns(2),
                    Section::make(__('assestme.settings.report.layout'))
                        ->schema([
                            TextInput::make('default_title_pattern')
                                ->label(__('assestme.settings.fields.default_title_pattern'))
                                ->helperText(__('assestme.settings.help.default_title_pattern'))
                                ->required()
                                ->live(debounce: 500)
                                ->extraInputAttributes(['data-dusk' => 'report-preview-title-input'])
                                ->maxLength(255),
                            ColorPicker::make('primary_color')
                                ->label(__('assestme.settings.fields.primary_color'))
                                ->helperText(__('assestme.settings.help.primary_color'))
                                ->required()
                                ->live(debounce: 300)
                                ->extraInputAttributes(['data-dusk' => 'report-preview-color-input'])
                                ->regex('/^#[0-9A-Fa-f]{6}$/'),
                            Select::make('branding')
                                ->label(__('assestme.settings.fields.branding'))
                                ->helperText(__('assestme.settings.help.branding'))
                                ->options([
                                    'consultant' => __('assestme.settings.values.consultant'),
                                    'client' => __('assestme.settings.values.client'),
                                    'both' => __('assestme.settings.values.both'),
                                ])
                                ->required()
                                ->live(),
                            Select::make('cover_title_mode')
                                ->label(__('assestme.settings.fields.cover_title_mode'))
                                ->helperText(__('assestme.settings.help.cover_title_mode'))
                                ->options(CoverTitleMode::options())
                                ->required()
                                ->live(),
                            Toggle::make('show_priority_descriptions')
                                ->label(__('assestme.settings.fields.show_priority_descriptions'))
                                ->helperText(__('assestme.settings.help.show_priority_descriptions'))
                                ->live(),
                            ...self::toggleFields(),
                        ])
                        ->columns(2),
                    Section::make(__('assestme.settings.report.output'))
                        ->schema([
                            TextInput::make('currency')
                                ->label(__('assestme.settings.fields.currency'))
                                ->helperText(__('assestme.settings.help.currency'))
                                ->required()
                                ->live(debounce: 500)
                                ->length(3)
                                ->regex('/^[A-Z]{3}$/'),
                            TextInput::make('currency_symbol')
                                ->label(__('assestme.settings.fields.currency_symbol'))
                                ->helperText(__('assestme.settings.help.currency_symbol'))
                                ->required()
                                ->live(debounce: 500)
                                ->maxLength(8),
                            Select::make('currency_symbol_position')
                                ->label(__('assestme.settings.fields.currency_symbol_position'))
                                ->helperText(__('assestme.settings.help.currency_symbol_position'))
                                ->options([
                                    'before' => __('assestme.settings.values.before'),
                                    'after' => __('assestme.settings.values.after'),
                                ])
                                ->required()
                                ->live(),
                            Select::make('currency_decimals')
                                ->label(__('assestme.settings.fields.currency_decimals'))
                                ->helperText(__('assestme.settings.help.currency_decimals'))
                                ->options([0 => '0', 2 => '2'])
                                ->required()
                                ->live(),
                            Toggle::make('report_excluded_findings_in_xlsx')
                                ->label(__('assestme.settings.fields.report_excluded_findings_in_xlsx'))
                                ->helperText(__('assestme.settings.help.report_excluded_findings_in_xlsx')),
                        ])
                        ->columns(2),
                    Section::make(__('assestme.settings.report.texts'))
                        ->schema([
                            Textarea::make('methodology_text')->label(__('assestme.settings.fields.methodology_text'))->helperText(__('assestme.settings.help.methodology_text'))->live(debounce: 500)->rows(5)->maxLength(20000),
                            Textarea::make('disclaimer_text')->label(__('assestme.settings.fields.disclaimer_text'))->helperText(__('assestme.settings.help.disclaimer_text'))->live(debounce: 500)->rows(5)->maxLength(20000),
                            Textarea::make('signature_text')->label(__('assestme.settings.fields.signature_text'))->helperText(__('assestme.settings.help.signature_text'))->live(debounce: 500)->rows(3)->maxLength(20000),
                        ]),
                ])
                    ->extraAttributes([
                        'class' => 'assestme-report-settings-options',
                        'data-dusk' => 'report-settings-options',
                    ])
                    ->columnSpan([
                        'default' => 1,
                        'xl' => 2,
                    ]),
                View::make('filament.pages.report-settings-preview')
                    ->columnSpan([
                        'default' => 1,
                        'xl' => 3,
                    ]),
            ]);
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return ['assestme-report-settings-page'];
    }

    public function reportPreviewUrl(): string
    {
        $raw = $this->form->getRawState();
        $settings = [];
        foreach (self::PREVIEW_FIELDS as $key) {
            $value = $raw[$key] ?? null;
            if ($key === 'consultant_logo_path' && is_array($value)) {
                $paths = array_values(array_filter($value, is_string(...)));
                $value = $paths[0] ?? null;
            }
            if (is_bool($value) || is_int($value) || is_string($value) || $value === null) {
                $settings[$key] = $value;
            }
        }
        $color = (string) ($settings['primary_color'] ?? '');
        $settings['primary_color'] = preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? mb_strtoupper($color) : '#65A30D';

        $token = Str::random(40);
        $sessionBinding = session()->get('report_preview_binding');
        if (! is_string($sessionBinding) || $sessionBinding === '') {
            $sessionBinding = Str::random(40);
            session()->put('report_preview_binding', $sessionBinding);
        }
        $key = sprintf(
            'report-preview:%d:%s:%s',
            (int) auth()->id(),
            hash('sha256', $sessionBinding),
            $token,
        );
        Cache::put($key, $settings, now()->addMinutes(5));

        return route('report-settings.preview', ['token' => $token]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $generalSettings = app(GeneralSettings::class)->toArray();
        foreach (self::GENERAL_REPORT_FIELDS as $field) {
            $data[$field] = $generalSettings[$field];
        }

        $mode = $data['cover_title_mode'] ?? CoverTitleMode::Separate;
        $data['cover_title_mode'] = $mode instanceof CoverTitleMode ? $mode->value : (string) $mode;

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingGeneralReportSettings = [];
        foreach (self::GENERAL_REPORT_FIELDS as $field) {
            $value = $data[$field];
            if (is_bool($value) || is_int($value) || is_string($value)) {
                $this->pendingGeneralReportSettings[$field] = $value;
            }
            unset($data[$field]);
        }
        $this->pendingGeneralReportSettings['currency'] = mb_strtoupper(
            (string) $this->pendingGeneralReportSettings['currency'],
        );

        foreach (['consultant_vat_number', 'consultant_tax_code'] as $field) {
            $value = $data[$field] ?? null;
            $data[$field] = is_string($value) && $value !== '' ? mb_strtoupper(str_replace(' ', '', $value)) : null;
        }

        $data['primary_color'] = mb_strtoupper((string) $data['primary_color']);
        $data['cover_title_mode'] = CoverTitleMode::from((string) $data['cover_title_mode']);

        return $data;
    }

    protected function afterSave(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->fill($this->pendingGeneralReportSettings);
        $settings->save();
    }

    /** @return list<Toggle> */
    private static function toggleFields(): array
    {
        $fields = [
            'cover', 'content_index', 'executive_summary', 'risk_legend', 'summary_table', 'methodology',
            'page_numbers', 'show_resolution', 'signature_block', 'disclaimer', 'technical_notes',
            'alternative_solutions', 'costs', 'evidence', 'evidence_captions',
            'freeze_after_generation',
        ];

        return array_map(static function (string $field): Toggle {
            $toggle = Toggle::make($field)
                ->label(__("assestme.settings.fields.{$field}"))
                ->helperText(__("assestme.settings.help.{$field}"));

            return $field === 'freeze_after_generation' ? $toggle : $toggle->live();
        }, $fields);
    }
}
