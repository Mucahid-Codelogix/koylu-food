<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Nieuw product';

    public function getSubheading(): ?string
    {
        return 'Stap 1 van 2: basisgegevens. Na opslaan voeg je verpakkingen en leveranciers toe.';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Product aangemaakt')
            ->body('Voeg nu verpakkingen en leveranciers toe in de tabbladen hieronder.');
    }
}
