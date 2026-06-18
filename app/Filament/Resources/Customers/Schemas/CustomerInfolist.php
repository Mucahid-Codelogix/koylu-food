<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Bedrijfsinformatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('company_name')
                            ->label('Bedrijfsnaam')
                            ->placeholder('-'),

                        TextEntry::make('contact_name')
                            ->label('Contactpersoon')
                            ->placeholder('-'),

                        IconEntry::make('is_active')
                            ->label('Actief')
                            ->boolean(),

                    ]),

                Section::make('Contactgegevens')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('email')
                            ->label('E-mailadres')
                            ->placeholder('-')
                            ->copyable(),

                        TextEntry::make('phone')
                            ->label('Telefoonnummer')
                            ->placeholder('-'),

                    ]),

                Section::make('Adresgegevens')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('address')
                            ->label('Adres')
                            ->placeholder('-'),

                        TextEntry::make('postal_code')
                            ->label('Postcode')
                            ->placeholder('-'),

                        TextEntry::make('city')
                            ->label('Plaats')
                            ->placeholder('-'),

                        TextEntry::make('country')
                            ->label('Land')
                            ->placeholder('-'),

                        TextEntry::make('vat_number')
                            ->label('BTW-nummer')
                            ->placeholder('-')
                            ->copyable(),

                    ]),

                Section::make('Bestelinstellingen')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('min_order_amount')
                            ->label('Minimale bestelhoeveelheid')
                            ->numeric(decimalPlaces: 2)
                            ->suffix(' kg')
                            ->placeholder('-'),

                        TextEntry::make('exact_article_suffix')
                            ->label('Artikeltoevoeging')
                            ->placeholder('-'),

                        IconEntry::make('is_vat_exempt')
                            ->label('BTW vrijgesteld')
                            ->boolean(),

                    ]),
            ]);
    }
}
