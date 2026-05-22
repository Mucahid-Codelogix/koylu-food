<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Productregel')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product (catalogus)')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false),

                        TextInput::make('product_name')
                            ->label('Productnaam (snapshot)')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('supplier_name')
                            ->label('Leverancier')
                            ->maxLength(255),

                        TextInput::make('packaging_label')
                            ->label('Verpakking / variant')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Hoeveelheid & prijs')
                    ->columns(2)
                    ->schema([
                        TextInput::make('unit')
                            ->label('Eenheid')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('quantity')
                            ->label('Aantal')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01),

                        TextInput::make('price_per_kg')
                            ->label('Prijs per kg')
                            ->numeric()
                            ->prefix('€')
                            ->step(0.0001),

                        TextInput::make('unit_price')
                            ->label('Stukprijs')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01),

                        TextInput::make('subtotal')
                            ->label('Subtotaal')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
