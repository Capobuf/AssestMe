<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

final class AssessmentWorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('assestme.workspace.assessment_section'))
                    ->columns(3)
                    ->schema([
                        Placeholder::make('client_display')
                            ->label(__('assestme.assessments.fields.client'))
                            ->content(fn (WorkspaceAssessment $livewire): string => self::assessment($livewire)->client->displayName()),
                        TextInput::make('title')
                            ->label(__('assestme.assessments.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpan(2),
                        DatePicker::make('assessment_date')
                            ->label(__('assestme.assessments.fields.date'))
                            ->required()
                            ->disabled(self::isReadOnly(...)),
                        TextInput::make('report_title_override')
                            ->label(__('assestme.assessments.fields.report_title'))
                            ->maxLength(255)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpan(2),
                        Select::make('scope_type')
                            ->label(__('assestme.assessments.fields.scope'))
                            ->options(self::scopeOptions())
                            ->live()
                            ->required()
                            ->disabled(self::isReadOnly(...)),
                        Select::make('site_ids')
                            ->label(__('assestme.assessments.fields.sites'))
                            ->options(fn (WorkspaceAssessment $livewire): array => self::assessment($livewire)->client->sites()->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                            ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                        Textarea::make('scope_description')
                            ->label(fn (Get $get): string => $get('scope_type') === ScopeType::Custom->value
                                ? __('assestme.assessments.fields.scope_description')
                                : __('assestme.assessments.fields.scope_notes'))
                            ->rows(4)
                            ->maxLength(20000)
                            ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::Custom->value)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                        Textarea::make('introduction')
                            ->label(__('assestme.assessments.fields.introduction'))
                            ->rows(6)
                            ->maxLength(20000)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                        Textarea::make('executive_summary')
                            ->label(__('assestme.assessments.fields.executive_summary'))
                            ->rows(6)
                            ->maxLength(20000)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                        Textarea::make('methodology_notes')
                            ->label(__('assestme.assessments.fields.methodology_notes'))
                            ->rows(6)
                            ->maxLength(20000)
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                        Placeholder::make('workspace_save_status')
                            ->label(__('assestme.workspace.save_state'))
                            ->content(fn (WorkspaceAssessment $livewire): View => view('filament.workspace-save-status', [
                                'status' => $livewire->saveStatus,
                                'label' => $livewire->getSaveStatusLabel(),
                            ]))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<string, string> */
    private static function scopeOptions(): array
    {
        return [
            ScopeType::Organization->value => __('assestme.scopes.organization'),
            ScopeType::SelectedSites->value => __('assestme.scopes.site'),
            ScopeType::Custom->value => __('assestme.scopes.custom'),
        ];
    }

    private static function isReadOnly(WorkspaceAssessment $livewire): bool
    {
        return $livewire->isWorkspaceReadOnly() || $livewire->saveStatus === WorkspaceAssessment::STATUS_CONFLICT;
    }

    private static function assessment(WorkspaceAssessment $livewire): Assessment
    {
        $record = $livewire->getRecord();

        if (! $record instanceof Assessment) {
            throw new \LogicException('The workspace record must be an assessment.');
        }

        return $record;
    }
}
