<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\Exact\ExactMatchService;
use App\Services\Exact\ExactOnlineClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

class MatchExactPaymentAction
{
    public static function make(): Action
    {
        return Action::make('matchExactPayment')
            ->label('Afletteren in Exact')
            ->icon('heroicon-o-check-circle')
            ->color('warning')
            ->visible(fn (Invoice $record): bool => $record->canBeMatchedInExact()
                && app(ExactOnlineClient::class)->isConnected())
            ->requiresConfirmation()
            ->modalHeading('Contante betaling afletteren')
            ->modalDescription('De kasboeking wordt in Exact tegen deze factuur weggestreept, zodat hij niet meer openstaat.')
            ->action(function (Invoice $record): void {
                try {
                    $matched = app(ExactMatchService::class)->matchCashPayment($record);
                } catch (Throwable $exception) {
                    $record->updateQuietly(['exact_match_error' => $exception->getMessage()]);

                    Notification::make()
                        ->title('Afletteren mislukt')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                if (! $matched) {
                    Notification::make()
                        ->title('Afletteren mislukt')
                        ->body($record->fresh()->exact_match_error ?? 'Exact heeft de aflettering geweigerd.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Betaling afgeletterd')
                    ->success()
                    ->send();
            });
    }
}
