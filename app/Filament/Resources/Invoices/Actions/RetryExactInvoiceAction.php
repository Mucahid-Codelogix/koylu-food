<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Jobs\PushInvoiceToExact;
use App\Models\Invoice;
use App\Services\Exact\ExactOnlineClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RetryExactInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('retryExact')
            ->label('Opnieuw boeken in Exact')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Invoice $record): bool => filled($record->exact_sync_error)
                && blank($record->exact_invoice_id)
                && app(ExactOnlineClient::class)->isConnected())
            ->requiresConfirmation()
            ->modalHeading('Opnieuw proberen in Exact')
            ->modalDescription('De mislukte boeking wordt opnieuw in de wachtrij gezet.')
            ->action(function (Invoice $record): void {
                $record->updateQuietly(['exact_sync_error' => null]);

                PushInvoiceToExact::dispatch($record);

                Notification::make()
                    ->title('Boeking opnieuw gestart')
                    ->success()
                    ->send();
            });
    }
}
