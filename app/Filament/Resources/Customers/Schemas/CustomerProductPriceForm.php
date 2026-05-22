<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\CustomerProductPrice;
use App\Models\ProductSupplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CustomerProductPriceForm
{
    public static function configure(Schema $schema, RelationManager $livewire): Schema
    {
        $customerId = $livewire->getOwnerRecord()->getKey();

        return $schema
            ->components([
                Section::make('Klantprijs')
                    ->description('Afwijkende prijs per kg voor deze klant en leverancier')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('product_supplier_id')
                            ->label('Product & leverancier')
                            ->options(fn (): array => self::productSupplierOptions($customerId))
                            ->getOptionLabelUsing(fn (int|string $value): string => self::productSupplierLabel((int) $value))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn (?CustomerProductPrice $record): bool => $record !== null)
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(function (?int $state, callable $set): void {
                                if ($state === null) {
                                    return;
                                }

                                $productId = ProductSupplier::query()
                                    ->whereKey($state)
                                    ->value('product_id');

                                $set('product_id', $productId);
                            })
                            ->helperText(fn (?CustomerProductPrice $record): ?string => $record !== null
                                ? 'Product en leverancier kunnen na aanmaken niet meer gewijzigd worden.'
                                : 'Alleen actieve combinaties zonder bestaande klantprijs.'),

                        TextInput::make('price')
                            ->label('Prijs per kg (klant)')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.0001)
                            ->minValue(0)
                            ->helperText(fn (Get $get): ?string => self::standardPriceHint($get('product_supplier_id'))),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function productSupplierOptions(int $customerId): array
    {
        $configuredIds = CustomerProductPrice::query()
            ->where('customer_id', $customerId)
            ->pluck('product_supplier_id');

        return ProductSupplier::query()
            ->where('is_active', true)
            ->whereNotIn('id', $configuredIds)
            ->with(['product:id,name', 'supplier:id,name'])
            ->get()
            ->sortBy(fn (ProductSupplier $offer): string => $offer->product->name.'|'.$offer->supplier->name)
            ->mapWithKeys(fn (ProductSupplier $offer): array => [
                $offer->id => self::formatProductSupplierLabel($offer),
            ])
            ->all();
    }

    private static function productSupplierLabel(int $productSupplierId): string
    {
        $offer = ProductSupplier::query()
            ->with(['product:id,name', 'supplier:id,name'])
            ->find($productSupplierId);

        return $offer !== null
            ? self::formatProductSupplierLabel($offer)
            : (string) $productSupplierId;
    }

    private static function formatProductSupplierLabel(ProductSupplier $offer): string
    {
        return sprintf(
            '%s — %s (standaard € %s/kg)',
            $offer->product->name,
            $offer->supplier->name,
            number_format((float) $offer->price_per_kg, 2, ',', '.'),
        );
    }

    private static function standardPriceHint(mixed $productSupplierId): ?string
    {
        if (blank($productSupplierId)) {
            return null;
        }

        $standard = ProductSupplier::query()
            ->whereKey($productSupplierId)
            ->value('price_per_kg');

        if ($standard === null) {
            return null;
        }

        return 'Standaardprijs in catalogus: € '.number_format((float) $standard, 2, ',', '.').'/kg';
    }
}
