<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RecalculateInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('recalculate')
            ->label('Herbereken concept')
            ->icon('heroicon-o-calculator')
            ->color('gray')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::CONCEPT)
            ->requiresConfirmation()
            ->modalHeading('Factuur herberekenen')
            ->modalDescription('Bedragen worden opnieuw berekend op basis van de actuele leveringsgegevens.')
            ->action(function (Invoice $record): void {
                app(InvoiceService::class)->refreshAmounts($record);

                Notification::make()
                    ->title('Factuur herberekend')
                    ->success()
                    ->send();
            });
    }
}
