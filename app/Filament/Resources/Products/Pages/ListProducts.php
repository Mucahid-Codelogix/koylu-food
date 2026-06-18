<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\ImportsProductsFromExact;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use ImportsProductsFromExact;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importFromExact')
                ->label('Importeer uit Exact')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Artikelen importeren uit Exact')
                ->modalDescription('Haalt alle verkoopartikelen op uit de gekoppelde Exact-administratie. Bestaande producten worden gekoppeld of bijgewerkt; nieuwe artikelen worden aangemaakt in de app. Verpakkingen en leveranciers moet je daarna handmatig instellen.')
                ->action(fn () => $this->importProductsFromExact()),
            CreateAction::make(),
        ];
    }
}
