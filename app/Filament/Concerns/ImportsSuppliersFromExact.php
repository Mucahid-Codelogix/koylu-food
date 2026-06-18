<?php

namespace App\Filament\Concerns;

use App\Jobs\ImportSuppliersFromExact;
use App\Services\Exact\ExactOnlineClient;
use Filament\Notifications\Notification;

trait ImportsSuppliersFromExact
{
    public function importSuppliersFromExact(): void
    {
        if (! app(ExactOnlineClient::class)->isConnected()) {
            Notification::make()
                ->title('Import mislukt')
                ->body('Koppel eerst Exact Online via Exact-koppeling.')
                ->danger()
                ->send();

            return;
        }

        ImportSuppliersFromExact::dispatch((int) auth()->id());

        Notification::make()
            ->title('Import gestart')
            ->body('Crediteuren worden uit Exact gehaald. Je ontvangt een melding wanneer het klaar is.')
            ->success()
            ->send();
    }
}
