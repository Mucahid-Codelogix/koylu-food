<?php

namespace App\Filament\Resources\Products\Pages;

class LoadingProductOrder extends ProductOrderPage
{
    protected static ?string $title = 'Laadvolgorde';

    protected static function sortColumn(): string
    {
        return 'loading_sort_order';
    }

    public function getSubheading(): ?string
    {
        return 'Sleep de producten in de volgorde waarin chauffeurs ze tijdens het laadproces laden.';
    }
}
