<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\AssetType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

final class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('assestme.assets.sections.assignment'))
                ->schema([
                    Select::make('client_id')
                        ->label(__('assestme.assets.fields.client'))
                        ->relationship('client', 'legal_name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('site_id', null))
                        ->required(),
                    Select::make('site_id')
                        ->label(__('assestme.assets.fields.site'))
                        ->relationship(
                            'site',
                            'name',
                            modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                ->where('client_id', $get('client_id')),
                        )
                        ->searchable()
                        ->preload(),
                    Select::make('asset_type_id')
                        ->label(__('assestme.assets.fields.asset_type'))
                        ->relationship(
                            'assetType',
                            'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort_order'),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (AssetType $record): string => $record->is_enabled
                                ? $record->name
                                : __('assestme.asset_types.disabled_option', ['name' => $record->name]),
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                ])->columns(['default' => 1, 'lg' => 3])->columnSpanFull(),
            Section::make(__('assestme.assets.sections.identification'))
                ->description(__('assestme.assets.identifier_help'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('assestme.assets.fields.name'))
                        ->maxLength(255),
                    TextInput::make('manufacturer')
                        ->label(__('assestme.assets.fields.manufacturer'))
                        ->maxLength(120),
                    TextInput::make('model')
                        ->label(__('assestme.assets.fields.model'))
                        ->maxLength(160),
                    TextInput::make('hostname')
                        ->label(__('assestme.assets.fields.hostname'))
                        ->maxLength(253),
                    TextInput::make('ip_address')
                        ->label(__('assestme.assets.fields.ip_address'))
                        ->ip()
                        ->maxLength(45),
                    TextInput::make('mac_address')
                        ->label(__('assestme.assets.fields.mac_address'))
                        ->placeholder('AA:BB:CC:DD:EE:FF')
                        ->maxLength(32),
                    TextInput::make('serial_number')
                        ->label(__('assestme.assets.fields.serial_number'))
                        ->maxLength(160),
                ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Section::make(__('assestme.assets.sections.details'))
                ->schema([
                    Textarea::make('description')
                        ->label(__('assestme.common.description'))
                        ->rows(4)
                        ->maxLength(20000),
                    Textarea::make('notes')
                        ->label(__('assestme.assets.fields.notes'))
                        ->rows(4)
                        ->maxLength(20000),
                ])->columns(1)->columnSpanFull(),
        ]);
    }
}
