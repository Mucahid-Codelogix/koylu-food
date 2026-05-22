<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Products\Schemas\ProductSupplierOfferForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductSuppliersRelationManager extends RelationManager
{
    protected static string $relationship = 'productSuppliers';

    protected static ?string $title = 'Leveranciers';

    protected static ?string $modelLabel = 'leverancier';

    protected static ?string $pluralModelLabel = 'leveranciers';

    public function form(Schema $schema): Schema
    {
        return ProductSupplierOfferForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('supplier.name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Nog geen leveranciers')
            ->emptyStateDescription('Koppel minstens één leverancier met een prijs per kg.')
            ->emptyStateIcon('heroicon-o-truck')
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Leverancier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_per_kg')
                    ->label('Prijs per kg')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('supplier_sku')
                    ->label('Artikelcode')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('exact_article_code')
                    ->label('Exact')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_default')
                    ->label('Standaard')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Leverancier toevoegen')
                    ->modalHeading('Leverancier koppelen')
                    ->modalWidth(Width::Large),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Leverancier bewerken')
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
