<?php

namespace App\Filament\Customer\Resources\Orders\RelationManagers;

use App\Filament\Resources\Orders\Schemas\OrderItemInfolist;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Bestelde producten';

    protected static ?string $modelLabel = 'productregel';

    public function infolist(Schema $schema): Schema
    {
        return OrderItemInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('supplier_name')
                    ->label('Leverancier')
                    ->placeholder('—'),
                TextColumn::make('packaging_label')
                    ->label('Verpakking')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Aantal')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('unit_price')
                    ->label('Stukprijs')
                    ->money('EUR'),
                TextColumn::make('subtotal')
                    ->label('Subtotaal')
                    ->money('EUR')
                    ->weight('medium'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
