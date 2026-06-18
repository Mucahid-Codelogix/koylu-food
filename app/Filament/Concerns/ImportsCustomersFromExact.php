<?php

namespace App\Filament\Concerns;

use App\Jobs\ImportCustomersFromExact;
use App\Services\Exact\ExactOnlineClient;
use Filament\Notifications\Notification;

trait ImportsCustomersFromExact
{
    public function importCustomersFromExact(): void
    {
        if (! app(ExactOnlineClient::class)->isConnected()) {
            Notification::make()
                ->title('Import mislukt')
                ->body('Koppel eerst Exact Online via Exact-koppeling.')
                ->danger()
                ->send();

            return;
        }

        ImportCustomersFromExact::dispatch((int) auth()->id());

        Notification::make()
            ->title('Import gestart')
            ->body('Debiteuren worden uit Exact gehaald. Je ontvangt een melding wanneer het klaar is.')
            ->success()
            ->send();
    }
}
