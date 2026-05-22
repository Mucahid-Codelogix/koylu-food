<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
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
                Section::make('Overzicht')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Productnaam')
                            ->size('large')
                            ->weight('bold')
                            ->columnSpan(2),

                        ImageEntry::make('image_path')
                            ->label('Afbeelding')
                            ->disk('public')
                            // ->height(200)
                            ->columnSpan(2)
                            ->defaultImageUrl('https://placehold.co/400x400?text=Geen+foto'),

                        TextEntry::make('product_type')
                            ->label('Type')
                            ->badge(),

                        IconEntry::make('allows_loading_substitute')
                            ->label('Alternatief bij laden')
                            ->boolean()
                            ->visible(fn (Product $record): bool => $record->isWholeChicken()),

                        IconEntry::make('is_active')
                            ->label('Zichtbaar in shop')
                            ->boolean(),

                        TextEntry::make('min_order_quantity')
                            ->label('Standaard min. afname')
                            ->numeric(decimalPlaces: 2),

                        TextEntry::make('gram_variants_count')
                            ->label('Gramvarianten')
                            ->state(fn (Product $record): string => (string) $record->gramVariants()->where('is_active', true)->count())
                            ->badge()
                            ->color('info')
                            ->visible(fn (Product $record): bool => $record->isWholeChicken()),

                        TextEntry::make('packagings_count')
                            ->label('Actieve verpakkingen')
                            ->state(fn (Product $record): string => (string) $record->packagings()->where('is_active', true)->count())
                            ->badge()
                            ->color('info')
                            ->visible(fn (Product $record): bool => ! $record->isWholeChicken()),

                        TextEntry::make('suppliers_count')
                            ->label('Actieve leveranciers')
                            ->state(fn (Product $record): string => (string) $record->productSuppliers()->where('is_active', true)->count())
                            ->badge()
                            ->color('info'),

                        TextEntry::make('default_packaging')
                            ->label('Standaardverpakking')
                            ->state(fn (Product $record): string => $record->defaultPackaging()?->displayLabel() ?? '—')
                            ->columnSpan(2),

                        TextEntry::make('default_supplier')
                            ->label('Standaardleverancier')
                            ->state(fn (Product $record): string => $record->defaultProductSupplier()?->supplier?->name ?? '—'),

                        TextEntry::make('default_price_per_kg')
                            ->label('Standaard prijs per kg')
                            ->money('EUR')
                            ->state(fn (Product $record): ?float => $record->defaultProductSupplier()?->price_per_kg),

                        TextEntry::make('description')
                            ->label('Omschrijving')
                            ->placeholder('Geen omschrijving')
                            ->prose()
                            ->columnSpanFull(),
                    ]),

                Section::make('Systeem')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Aangemaakt')
                            ->dateTime('d-m-Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Bijgewerkt')
                            ->dateTime('d-m-Y H:i'),
                    ]),
            ]);
    }
}
