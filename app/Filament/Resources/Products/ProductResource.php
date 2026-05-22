<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\GramVariantsRelationManager;
use App\Filament\Resources\Products\RelationManagers\PackagingsRelationManager;
use App\Filament\Resources\Products\RelationManagers\ProductSuppliersRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Schemas\ProductInfolist;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingCart;

    protected static ?string $navigationLabel = 'Producten';

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Producten';

    protected static string|null|\UnitEnum $navigationGroup = 'Assortiment';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProductInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            GramVariantsRelationManager::class,
            PackagingsRelationManager::class,
            ProductSuppliersRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['packagings', 'productSuppliers']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
