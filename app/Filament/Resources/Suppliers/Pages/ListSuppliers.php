<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Concerns\ImportsSuppliersFromExact;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    use ImportsSuppliersFromExact;

    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importFromExact')
                ->label('Importeer uit Exact')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Leveranciers importeren uit Exact')
                ->modalDescription('Haalt alle crediteuren op uit de gekoppelde Exact-administratie. Bestaande leveranciers worden gekoppeld of bijgewerkt; nieuwe crediteuren worden aangemaakt in de app.')
                ->action(fn () => $this->importSuppliersFromExact()),
            CreateAction::make(),
        ];
    }
}
