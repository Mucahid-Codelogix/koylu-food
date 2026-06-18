<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Concerns\ImportsCustomersFromExact;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use ImportsCustomersFromExact;

    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importFromExact')
                ->label('Importeer uit Exact')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Klanten importeren uit Exact')
                ->modalDescription('Haalt alle debiteuren op uit de gekoppelde Exact-administratie. Bestaande klanten worden gekoppeld of bijgewerkt; nieuwe debiteuren worden aangemaakt in de app.')
                ->action(fn () => $this->importCustomersFromExact()),
            CreateAction::make(),
        ];
    }
}
