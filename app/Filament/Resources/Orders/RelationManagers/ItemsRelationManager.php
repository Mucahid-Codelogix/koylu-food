<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Filament\Resources\Orders\Schemas\OrderItemForm;
use App\Filament\Resources\Orders\Schemas\OrderItemInfolist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Orderregels';

    protected static ?string $modelLabel = 'orderregel';

    public function form(Schema $schema): Schema
    {
        return OrderItemForm::configure($schema);
    }

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
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('packaging_label')
                    ->label('Verpakking')
                    ->placeholder('—'),
                TextColumn::make('quantity')
                    ->label('Aantal')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Stukprijs')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('subtotal')
                    ->label('Subtotaal')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Regel toevoegen'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
