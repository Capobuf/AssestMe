<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Schemas;

use App\Filament\Components\RiskMatrixField;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class RiskProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Tabs::make('risk-profile-configuration')
                ->activeTab(1)
                ->tabs([
                    Tab::make(__('assestme.risk.sections.matrix'))
                        ->schema([
                            Section::make(__('assestme.risk.sections.matrix'))
                                ->description(__('assestme.risk.matrix_help'))
                                ->schema([
                                    RiskMatrixField::make('matrix')
                                        ->label(__('assestme.risk.sections.matrix'))
                                        ->hiddenLabel()
                                        ->required()
                                        ->consequences(fn (Get $get): array => self::levelRows($get('consequences')))
                                        ->likelihoods(fn (Get $get): array => self::levelRows($get('likelihoods')))
                                        ->priorities(fn (Get $get): array => self::levelRows($get('priorities'))),
                                ]),
                        ]),
                    Tab::make(__('assestme.risk.sections.consequences'))
                        ->schema([
                            Section::make(__('assestme.risk.sections.consequences'))->schema([
                                self::scoredLevelRepeater('consequences'),
                            ]),
                        ]),
                    Tab::make(__('assestme.risk.sections.likelihoods'))
                        ->schema([
                            Section::make(__('assestme.risk.sections.likelihoods'))->schema([
                                self::scoredLevelRepeater('likelihoods'),
                            ]),
                        ]),
                    Tab::make(__('assestme.risk.sections.priorities'))
                        ->schema([
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
                        ]),
                    Tab::make(__('assestme.risk.sections.profile'))
                        ->schema([
                            Section::make(__('assestme.risk.sections.profile'))->schema([
                                TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set, mixed $state) => self::proposeCode($get, $set, $state)),
                                Toggle::make('is_default')->label(__('assestme.risk.fields.default'))->required(),
                                Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true),
                                Textarea::make('description')->label(__('assestme.common.description'))->rows(3)->maxLength(20000)->columnSpanFull(),
                            ])->columns(['default' => 1, 'md' => 2]),
                            Section::make(__('assestme.templates.sections.advanced'))
                                ->collapsed()
                                ->schema([
                                    TextInput::make('code')->label(__('assestme.common.code'))->disabled()->dehydrated(),
                                ]),
                        ]),
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
            Hidden::make('_form_key')
                ->default(static fn (Component $component): string => (string) str($component->getStatePath())
                    ->beforeLast('.')
                    ->afterLast('.')),
            TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                    self::proposeCode($get, $set, $state);
                }),
            Textarea::make('description')->label(__('assestme.common.description'))->rows(2)->maxLength(20000),
        ];

        if ($includeScore) {
            $fields[] = TextInput::make('score')->label(__('assestme.risk.fields.score'))->numeric()->minValue(1)->maxValue(4)->required();
        }

        $fields[] = ColorPicker::make('color')->label(__('assestme.common.color'))->required()->regex('/^#[0-9A-Fa-f]{6}$/')->live();
        $fields[] = TextInput::make('sort_order')->label(__('assestme.common.sort_order'))->numeric()->minValue(0)->required()->live(onBlur: true);
        $fields[] = Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true)->live();
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
        $consequences = self::levelRows($get('consequences'));
        $likelihoods = self::levelRows($get('likelihoods'));
        $priorities = self::levelRows($get('priorities'));
        if (count($consequences) !== 4 || count($likelihoods) !== 4 || $priorities === []) {
            return;
        }

        $existing = is_array($get('matrix')) ? $get('matrix') : [];
        $firstPriorityKey = array_key_first($priorities);
        $defaultPriority = self::levelIdentity($priorities[$firstPriorityKey], $firstPriorityKey);
        $matrix = [];
        foreach ($consequences as $consequenceFormKey => $consequence) {
            $consequenceIdentity = self::levelIdentity($consequence, $consequenceFormKey);
            foreach ($likelihoods as $likelihoodFormKey => $likelihood) {
                $likelihoodIdentity = self::levelIdentity($likelihood, $likelihoodFormKey);
                $matrix[$consequenceIdentity][$likelihoodIdentity] = $existing[$consequenceIdentity][$likelihoodIdentity]
                    ?? $defaultPriority;
            }
        }

        $set('matrix', $matrix);
    }

    /** @return array<int|string, array<string, mixed>> */
    private static function levelRows(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        return array_filter($state, static fn (mixed $row): bool => is_array($row));
    }

    /** @param array<string, mixed> $row */
    private static function levelIdentity(array $row, int|string|null $formKey): string
    {
        if (filled($row['id'] ?? null)) {
            return (string) $row['id'];
        }

        return (string) ($row['_form_key'] ?? $formKey ?? '');
    }
}
