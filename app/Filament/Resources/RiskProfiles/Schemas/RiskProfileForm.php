<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class RiskProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.risk.sections.profile'))->schema([
                TextInput::make('code')->label(__('assestme.common.code'))->required()->maxLength(40)->regex('/^[a-z0-9_]+$/'),
                TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120),
                Toggle::make('is_default')->label(__('assestme.risk.fields.default'))->required(),
                Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true),
                Textarea::make('description')->label(__('assestme.common.description'))->rows(3)->maxLength(20000)->columnSpanFull(),
            ])->columns(2),
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
                    ->columns(6)
                    ->required(),
            ]),
            Section::make(__('assestme.risk.sections.matrix'))->description(__('assestme.risk.matrix_help'))->schema([
                Repeater::make('matrix')
                    ->label(__('assestme.risk.sections.matrix'))
                    ->schema([
                        TextInput::make('consequence_code')->label(__('assestme.risk.fields.consequence_code'))->required()->maxLength(40),
                        TextInput::make('likelihood_code')->label(__('assestme.risk.fields.likelihood_code'))->required()->maxLength(40),
                        TextInput::make('priority_code')->label(__('assestme.risk.fields.priority_code'))->required()->maxLength(40),
                    ])
                    ->minItems(16)
                    ->maxItems(16)
                    ->columns(3)
                    ->required(),
            ]),
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
            ->columns(7)
            ->required();
    }

    /** @return list<TextInput|ColorPicker|Toggle> */
    private static function levelFields(bool $includeScore): array
    {
        $fields = [
            TextInput::make('code')->label(__('assestme.common.code'))->required()->maxLength(40)->regex('/^[a-z0-9_]+$/'),
            TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120),
        ];

        if ($includeScore) {
            $fields[] = TextInput::make('score')->label(__('assestme.risk.fields.score'))->numeric()->minValue(1)->maxValue(4)->required();
        }

        $fields[] = ColorPicker::make('color')->label(__('assestme.common.color'))->required()->regex('/^#[0-9A-Fa-f]{6}$/');
        $fields[] = TextInput::make('sort_order')->label(__('assestme.common.sort_order'))->numeric()->minValue(0)->required();
        $fields[] = Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true);

        return $fields;
    }
}
