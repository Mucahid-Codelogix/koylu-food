<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Customers\Schemas\CustomerProductPriceForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerProductPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'productPrices';

    protected static ?string $title = 'Klantprijzen';

    protected static ?string $modelLabel = 'klantprijs';

    protected static ?string $pluralModelLabel = 'klantprijzen';

    public function form(Schema $schema): Schema
    {
        return CustomerProductPriceForm::configure($schema, $this);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product.name')
            ->defaultSort('product.name')
            ->emptyStateHeading('Geen klantprijzen')
            ->emptyStateDescription('Stel per product en leverancier een afwijkende prijs per kg in. Zonder regel geldt de standaardprijs uit de catalogus.')
            ->emptyStateIcon('heroicon-o-currency-euro')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productSupplier.supplier.name')
                    ->label('Leverancier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('productSupplier.price_per_kg')
                    ->label('Standaard €/kg')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Klant €/kg')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Klantprijs toevoegen')
                    ->modalHeading('Klantprijs instellen')
                    ->modalWidth(Width::Large),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Klantprijs bewerken')
                    ->modalWidth(Width::Large),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
