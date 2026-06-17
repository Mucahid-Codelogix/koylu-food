<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;

class InvoicePdfAction
{
    public static function makeOpenAction(): Action
    {
        return Action::make('open_pdf')
            ->label('PDF openen')
            ->icon('heroicon-o-document-arrow-down')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::CONCEPT
                || $record->pdf_path !== null)
            ->action(function (Invoice $record, $livewire): void {
                if ($record->status === InvoiceStatus::CONCEPT) {
                    self::generateForConcept($record);
                    $record->refresh();
                }

                $url = route('invoice.pdf', $record);
                $livewire->js('window.open('.json_encode($url).', "_blank")');
            });
    }

    public static function generateForConcept(Invoice $invoice): Invoice
    {
        $invoice = $invoice->fresh([
            'order.customer',
            'order.items.product',
            'order.delivery.items',
        ]);

        app(InvoiceService::class)->generatePdf($invoice);

        return $invoice->refresh();
    }
}
