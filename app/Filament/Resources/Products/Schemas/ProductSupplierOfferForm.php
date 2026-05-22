<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductSupplierOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Leverancier & prijs')
                    ->description('Catalogusprijs per kg voor dit product')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Leverancier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        TextInput::make('price_per_kg')
                            ->label('Prijs per kg')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.0001)
                            ->minValue(0),

                        TextInput::make('supplier_sku')
                            ->label('Artikelcode leverancier')
                            ->maxLength(255),

                        TextInput::make('exact_article_code')
                            ->label('Exact artikelcode')
                            ->maxLength(255),

                        TextInput::make('sort_order')
                            ->label('Volgorde')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_default')
                            ->label('Standaardleverancier')
                            ->helperText('Wordt voorgeselecteerd in de shop')
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
