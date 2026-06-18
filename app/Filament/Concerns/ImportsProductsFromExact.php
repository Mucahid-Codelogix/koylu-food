<?php

namespace App\Filament\Concerns;

use App\Jobs\ImportProductsFromExact;
use App\Services\Exact\ExactOnlineClient;
use Filament\Notifications\Notification;

trait ImportsProductsFromExact
{
    public function importProductsFromExact(): void
    {
        if (! app(ExactOnlineClient::class)->isConnected()) {
            Notification::make()
                ->title('Import mislukt')
                ->body('Koppel eerst Exact Online via Exact-koppeling.')
                ->danger()
                ->send();

            return;
        }

        ImportProductsFromExact::dispatch((int) auth()->id());

        Notification::make()
            ->title('Import gestart')
            ->body('Artikelen worden uit Exact gehaald. Je ontvangt een melding wanneer het klaar is.')
            ->success()
            ->send();
    }
}
