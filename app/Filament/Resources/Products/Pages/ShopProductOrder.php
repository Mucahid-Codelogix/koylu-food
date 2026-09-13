<?php

namespace App\Filament\Resources\Products\Pages;

class ShopProductOrder extends ProductOrderPage
{
    protected static ?string $title = 'Shopvolgorde';

    protected static function sortColumn(): string
    {
        return 'shop_sort_order';
    }

    public function getSubheading(): ?string
    {
        return 'Sleep de producten in de volgorde waarin klanten ze in de shop zien.';
    }
}
