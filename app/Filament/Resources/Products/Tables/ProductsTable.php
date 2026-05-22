<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('packagings_count')
                    ->label('Verpakkingen')
                    ->counts('packagings'),
                TextColumn::make('product_suppliers_count')
                    ->label('Leveranciers')
                    ->counts('productSuppliers'),
                TextColumn::make('default_price_per_kg')
                    ->label('Prijs/kg')
                    ->state(function ($record): ?string {
                        $price = $record->defaultProductSupplier()?->price_per_kg;

                        return $price !== null
                            ? '€ '.number_format((float) $price, 2, ',', '.')
                            : null;
                    }),
                TextColumn::make('min_order_quantity')
                    ->label('Min. afname')
                    ->numeric(decimalPlaces: 2),
                ImageColumn::make('image_path')->disk('public'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
