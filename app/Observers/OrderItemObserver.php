<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Services\InvoiceService;

/**
 * Alleen de gecachte factuurbedragen worden bijgewerkt. De PDF/UBL worden niet
 * hier gegenereerd: de conceptfactuur-PDF wordt bij elke keer openen opnieuw
 * opgebouwd en rekent live met de actuele orderregels.
 */
class OrderItemObserver
{
    public function __construct(
        protected InvoiceService $invoices,
    ) {}

    public function saved(OrderItem $orderItem): void
    {
        $this->refreshConceptInvoice($orderItem);
    }

    public function deleted(OrderItem $orderItem): void
    {
        $this->refreshConceptInvoice($orderItem);
    }

    /**
     * Houdt de gekoppelde conceptfactuur in sync wanneer een orderregel wijzigt
     * (bijv. prijs per kg). Verzonden/betaalde facturen blijven ongemoeid.
     */
    private function refreshConceptInvoice(OrderItem $orderItem): void
    {
        // Bewust een verse query (niet via $orderItem->order->invoice): bij een
        // tweede save op dezelfde instance kan die relatie nog als null gecachet zijn.
        $invoice = Invoice::query()
            ->where('order_id', $orderItem->order_id)
            ->first();

        if ($invoice === null || ! $invoice->isConcept()) {
            return;
        }

        $this->invoices->refreshAmounts($invoice);
    }
}
