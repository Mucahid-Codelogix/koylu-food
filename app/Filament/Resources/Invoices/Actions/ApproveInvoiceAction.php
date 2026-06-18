<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Jobs\PushInvoiceToExact;
use App\Models\Invoice;
use App\Services\Exact\ExactOnlineClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ApproveInvoiceAction
{
    public static function make(?\Closure $after = null): Action
    {
        return Action::make('approve')
            ->label('Goedkeuren & boeken in Exact')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::CONCEPT
                && app(ExactOnlineClient::class)->isConnected())
            ->requiresConfirmation()
            ->modalHeading('Factuur goedkeuren en boeken in Exact')
            ->modalDescription('De factuur wordt herberekend, geboekt in Exact, en daarna worden PDF en UBL aangemaakt met het Exact-factuurnummer.')
            ->action(function (Invoice $record) use ($after): void {
                PushInvoiceToExact::dispatch($record);

                Notification::make()
                    ->title('Factuurboeking gestart')
                    ->body('De factuur wordt naar Exact geboekt. PDF en UBL volgen zodra het Exact-nummer bekend is.')
                    ->success()
                    ->send();

                if ($after) {
                    $after($record);
                }
            });
    }
}
