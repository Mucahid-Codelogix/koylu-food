<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'productSuppliers';

    protected static ?string $title = 'Producten';

    protected static ?string $modelLabel = 'product';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Productkoppeling')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->columnSpanFull(),

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

                        Toggle::make('is_default')
                            ->label('Standaardleverancier')
                            ->default(false)
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('price_per_kg')
                    ->label('Prijs per kg')
                    ->money('EUR')
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Standaard')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
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
