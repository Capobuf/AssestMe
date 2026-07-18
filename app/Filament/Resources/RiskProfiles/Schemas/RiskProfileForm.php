<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class RiskProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('assestme.risk.sections.profile'))->schema([
                TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => self::proposeCode($get, $set, $state)),
                Toggle::make('is_default')->label(__('assestme.risk.fields.default'))->required(),
                Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true),
                Textarea::make('description')->label(__('assestme.common.description'))->rows(3)->maxLength(20000)->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Section::make(__('assestme.risk.sections.consequences'))->schema([
                self::scoredLevelRepeater('consequences'),
            ]),
            Section::make(__('assestme.risk.sections.likelihoods'))->schema([
                self::scoredLevelRepeater('likelihoods'),
            ]),
            Section::make(__('assestme.risk.sections.priorities'))->schema([
                Repeater::make('priorities')
                    ->label(__('assestme.risk.sections.priorities'))
                    ->schema(self::levelFields(includeScore: false))
                    ->minItems(1)
                    ->reorderable()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::synchronizeMatrix($get, $set))
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->required(),
            ]),
            Section::make(__('assestme.risk.sections.matrix'))->description(__('assestme.risk.matrix_help'))->schema([
                ViewField::make('matrix')
                    ->hiddenLabel()
                    ->view('filament.risk-matrix')
                    ->viewData(fn (Get $get): array => [
                        'consequences' => array_values(is_array($get('consequences')) ? $get('consequences') : []),
                        'likelihoods' => array_values(is_array($get('likelihoods')) ? $get('likelihoods') : []),
                        'priorities' => array_values(is_array($get('priorities')) ? $get('priorities') : []),
                    ]),
            ])->columnSpanFull(),
            Section::make(__('assestme.templates.sections.advanced'))
                ->collapsed()
                ->schema([
                    TextInput::make('code')->label(__('assestme.common.code'))->disabled()->dehydrated(),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function scoredLevelRepeater(string $name): Repeater
    {
        return Repeater::make($name)
            ->label(__("assestme.risk.sections.{$name}"))
            ->schema(self::levelFields(includeScore: true))
            ->minItems(4)
            ->maxItems(4)
            ->reorderable()
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Get $get, Set $set) => self::synchronizeMatrix($get, $set))
            ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
            ->required();
    }

    /** @return list<Hidden|TextInput|Textarea|ColorPicker|Toggle> */
    private static function levelFields(bool $includeScore): array
    {
        $fields = [
            Hidden::make('id'),
            TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    self::proposeCode($get, $set, $state);
                    self::synchronizeMatrix($get, $set);
                }),
            Textarea::make('description')->label(__('assestme.common.description'))->rows(2)->maxLength(20000),
        ];

        if ($includeScore) {
            $fields[] = TextInput::make('score')->label(__('assestme.risk.fields.score'))->numeric()->minValue(1)->maxValue(4)->required();
        }

        $fields[] = ColorPicker::make('color')->label(__('assestme.common.color'))->required()->regex('/^#[0-9A-Fa-f]{6}$/');
        $fields[] = TextInput::make('sort_order')->label(__('assestme.common.sort_order'))->numeric()->minValue(0)->required();
        $fields[] = Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true);
        $fields[] = TextInput::make('code')->label(__('assestme.common.code'))->disabled()->dehydrated();

        return $fields;
    }

    private static function proposeCode(Get $get, Set $set, mixed $state): void
    {
        if (filled($get('id')) || filled($get('code'))) {
            return;
        }

        $code = Str::slug(Str::lower(trim((string) $state)), '_');
        $set('code', $code === '' ? 'livello' : Str::limit($code, 40, ''));
    }

    private static function synchronizeMatrix(Get $get, Set $set): void
    {
        $consequences = array_values(is_array($get('consequences')) ? $get('consequences') : []);
        $likelihoods = array_values(is_array($get('likelihoods')) ? $get('likelihoods') : []);
        $priorities = array_values(is_array($get('priorities')) ? $get('priorities') : []);
        if (count($consequences) !== 4 || count($likelihoods) !== 4 || $priorities === []) {
            return;
        }

        $existing = collect(is_array($get('matrix')) ? $get('matrix') : [])->keyBy(
            static fn (array $row): string => ($row['consequence_code'] ?? '').'|'.($row['likelihood_code'] ?? ''),
        );
        $defaultPriority = (string) ($priorities[0]['code'] ?? '');
        $matrix = [];
        foreach ($consequences as $consequence) {
            foreach ($likelihoods as $likelihood) {
                $consequenceCode = (string) ($consequence['code'] ?? '');
                $likelihoodCode = (string) ($likelihood['code'] ?? '');
                $saved = $existing->get($consequenceCode.'|'.$likelihoodCode);
                $matrix[] = [
                    'consequence_code' => $consequenceCode,
                    'likelihood_code' => $likelihoodCode,
                    'priority_code' => is_array($saved) ? ($saved['priority_code'] ?? $defaultPriority) : $defaultPriority,
                ];
            }
        }

        $set('matrix', $matrix);
    }
}
