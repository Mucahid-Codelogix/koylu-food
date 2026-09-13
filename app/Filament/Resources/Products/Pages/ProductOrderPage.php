<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sleeppagina voor één productvolgorde. Shop en laadproces hebben elk een eigen
 * volgorde, omdat de shop commercieel en het laden logistiek wordt ingedeeld.
 */
abstract class ProductOrderPage extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /**
     * Kolom op products waarin deze volgorde staat.
     */
    abstract protected static function sortColumn(): string;

    public function getBreadcrumb(): ?string
    {
        return static::$title;
    }

    public function table(Table $table): Table
    {
        $column = static::sortColumn();

        return $table
            ->defaultSort($column)
            ->reorderable($column)
            ->paginated(false)
            ->columns([
                TextColumn::make($column)
                    ->label('#')
                    ->width('4rem'),
                TextColumn::make('name')
                    ->label('Product'),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToProducts')
                ->label('Terug naar producten')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProductResource::getUrl('index')),
        ];
    }
}
