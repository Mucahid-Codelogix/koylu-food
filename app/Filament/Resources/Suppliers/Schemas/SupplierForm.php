<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bedrijfsgegevens')
                    ->icon('heroicon-o-building-office')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Bedrijfsnaam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),

                        TextInput::make('vat_number')
                            ->label('BTW-nummer')
                            ->maxLength(255),

                        TextInput::make('kvk_number')
                            ->label('KvK-nummer')
                            ->maxLength(255),
                    ]),

                Section::make('Contactgegevens')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_person')
                            ->label('Contactpersoon')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Adres')
                    ->schema([
                        TextInput::make('address')
                            ->label('Adres')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
