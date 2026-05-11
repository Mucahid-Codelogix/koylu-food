<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Bedrijfsinformatie')
                    ->columns(2)
                    ->schema([

                        TextInput::make('company_name')
                            ->label('Bedrijfsnaam')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('contact_name')
                            ->label('Contactpersoon')
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true),

                    ]),

                Section::make('Contactgegevens')
                    ->columns(2)
                    ->schema([

                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Telefoonnummer')
                            ->tel()
                            ->maxLength(255),

                    ]),

                Section::make('Adresgegevens')
                    ->columns(2)
                    ->schema([

                        TextInput::make('address')
                            ->label('Adres')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('postal_code')
                            ->label('Postcode')
                            ->required()
                            ->maxLength(50),

                        TextInput::make('city')
                            ->label('Plaats')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('country')
                            ->label('Landcode')
                            ->required()
                            ->default('NL')
                            ->maxLength(10),

                    ]),

                Section::make('Bestelinstellingen')
                    ->columns(2)
                    ->schema([

                        TextInput::make('min_order_amount')
                            ->label('Minimale bestelhoeveelheid')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->suffix('kg'),

                        TextInput::make('exact_article_suffix')
                            ->label('Artikeltoevoeging')
                            ->maxLength(50),

                        Toggle::make('is_vat_exempt')
                            ->label('BTW vrijgesteld')
                            ->default(false),

                    ]),
            ]);
    }
}
