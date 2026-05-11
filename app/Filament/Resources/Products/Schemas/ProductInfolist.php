<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product informatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('name')
                            ->label('Productnaam')
                            ->size('large')
                            ->weight('bold'),

                        TextEntry::make('supplier.name')
                            ->label('Leverancier')
                            ->placeholder('-')
                            ->badge(),

                        TextEntry::make('unit')
                            ->label('Eenheid')
                            ->badge(),

                        TextEntry::make('price')
                            ->label('Verkoopprijs')
                            ->money('EUR'),

                        TextEntry::make('min_quantity')
                            ->label('Minimale afname')
                            ->numeric(),

                        IconEntry::make('is_active')
                            ->label('Actief')
                            ->boolean(),

                        TextEntry::make('description')
                            ->label('Omschrijving')
                            ->placeholder('Geen omschrijving')
                            ->prose()
                            ->columnSpanFull(),

                    ]),

                Section::make('Product afbeelding')
                    ->schema([

                        ImageEntry::make('image_path')
                            ->label('afbeelding')
                            ->disk('public')
                            ->height(300)
                            ->square(false)
                            ->defaultImageUrl(
                                'https://placehold.co/800x500?text=Geen+afbeelding'
                            ),

                    ]),

                Section::make('Systeem informatie')
                    ->columns(2)
                    ->collapsed()
                    ->schema([

                        TextEntry::make('created_at')
                            ->label('Aangemaakt op')
                            ->dateTime('d-m-Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Laatst bijgewerkt')
                            ->dateTime('d-m-Y H:i'),

                    ]),

            ]);

    }
}
