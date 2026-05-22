<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\OrderItem;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Productregel')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('product.name')
                            ->label('Product (catalogus)')
                            ->placeholder('-'),

                        TextEntry::make('product_name')
                            ->label('Productnaam')
                            ->weight('medium'),

                        TextEntry::make('supplier_name')
                            ->label('Leverancier')
                            ->placeholder('-'),

                        TextEntry::make('packaging_label')
                            ->label('Verpakking / variant')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Hoeveelheid & prijs')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('unit')
                            ->label('Eenheid'),

                        TextEntry::make('quantity')
                            ->label('Aantal')
                            ->numeric(decimalPlaces: 2),

                        TextEntry::make('price_per_kg')
                            ->label('Prijs per kg')
                            ->money('EUR')
                            ->placeholder('-'),

                        TextEntry::make('unit_price')
                            ->label('Stukprijs')
                            ->money('EUR'),

                        TextEntry::make('subtotal')
                            ->label('Subtotaal')
                            ->money('EUR')
                            ->weight('bold'),
                    ]),

                Section::make('Levering')
                    ->columns(2)
                    ->visible(fn (OrderItem $record): bool => $record->loaded_at !== null)
                    ->schema([
                        TextEntry::make('loaded_gram_variant_id')
                            ->label('Geladen variant')
                            ->state(fn (OrderItem $record): string => $record->loadedGramVariant?->displayLabel() ?? '—')
                            ->visible(fn (OrderItem $record): bool => $record->isWholeChicken()),

                        TextEntry::make('loaded_total_weight_kg')
                            ->label('Geladen gewicht')
                            ->suffix(' kg')
                            ->numeric(decimalPlaces: 3)
                            ->placeholder('-'),

                        TextEntry::make('loaded_at')
                            ->label('Geladen op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                    ]),

                Section::make('Systeem')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Aangemaakt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Bijgewerkt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
