<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('assestme.clients.sections.identity'))
                    ->schema([
                        TextInput::make('legal_name')
                            ->label(__('assestme.clients.fields.legal_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('trade_name')
                            ->label(__('assestme.clients.fields.trade_name'))
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label(__('assestme.clients.fields.vat_number'))
                            ->maxLength(32),
                        TextInput::make('tax_code')
                            ->label(__('assestme.clients.fields.tax_code'))
                            ->maxLength(32),
                    ])->columns(2),
                Section::make(__('assestme.clients.sections.contacts'))
                    ->schema([
                        TextInput::make('email')
                            ->label(__('assestme.clients.fields.email'))
                            ->email()
                            ->maxLength(254),
                        TextInput::make('phone')
                            ->label(__('assestme.clients.fields.phone'))
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('website')
                            ->label(__('assestme.clients.fields.website'))
                            ->url()
                            ->maxLength(2048),
                    ])->columns(2),
                Section::make(__('assestme.address.section'))
                    ->schema(self::addressFields())
                    ->columns(2),
                Section::make(__('assestme.clients.sections.additional'))
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label(__('assestme.clients.fields.logo'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg'])
                            ->maxSize(5120)
                            ->directory('clients/logos')
                            ->visibility('private'),
                        Textarea::make('internal_notes')
                            ->label(__('assestme.clients.fields.internal_notes'))
                            ->rows(4)
                            ->maxLength(20000),
                    ]),
            ]);
    }

    /** @return list<TextInput> */
    private static function addressFields(): array
    {
        return [
            TextInput::make('address')
                ->label(__('assestme.address.fields.address'))
                ->maxLength(255),
            TextInput::make('city')
                ->label(__('assestme.address.fields.city'))
                ->maxLength(255),
            TextInput::make('postal_code')
                ->label(__('assestme.address.fields.postal_code'))
                ->maxLength(20),
            TextInput::make('province')
                ->label(__('assestme.address.fields.province'))
                ->maxLength(100),
            TextInput::make('country')
                ->label(__('assestme.address.fields.country'))
                ->required()
                ->default('IT')
                ->length(2),
        ];
    }
}
