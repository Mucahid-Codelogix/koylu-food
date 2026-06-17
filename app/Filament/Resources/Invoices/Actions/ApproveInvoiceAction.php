<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ApproveInvoiceAction
{
    public static function make(?\Closure $after = null): Action
    {
        return Action::make('approve')
            ->label('Goedkeuren & versturen')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::CONCEPT)
            ->requiresConfirmation()
            ->modalHeading('Factuur goedkeuren')
            ->modalDescription('PDF en UBL worden aangemaakt en de factuur wordt verzonden. Weet je zeker dat je wilt doorgaan?')
            ->action(function (Invoice $record) use ($after): void {
                $service = app(InvoiceService::class);
                $service->generatePdf($record);
                $service->generateUbl($record);

                $record->update([
                    'status' => InvoiceStatus::SENT,
                    'sent_at' => now(),
                ]);

                Notification::make()
                    ->title('Factuur goedgekeurd!')
                    ->success()
                    ->send();

                if ($after) {
                    $after($record);
                }
            });
    }
}
