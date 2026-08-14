<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Jobs\ProcessCashPaidInvoice;
use App\Models\Invoice;

/**
 * Contant betaalde leveringen worden direct afgehandeld: de factuur is meteen
 * definitief en betaald, wordt geboekt in Exact en naar de klant gemaild.
 * Dit wijkt bewust af van de normale route, waarbij een admin eerst goedkeurt.
 */
class CashPaymentService
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function registerCashPayment(Invoice $invoice, ?float $amount = null): Invoice
    {
        $invoice = $this->invoiceService->refreshAmounts($invoice);

        $invoice->update([
            'payment_method' => PaymentMethod::CASH,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'paid_amount' => $amount ?? (float) $invoice->total_amount,
        ]);

        /**
         * De PDF maken we meteen, zodat de klant zijn factuur altijd kan
         * downloaden — ook als de queue-worker stilstaat. Zodra de factuur in
         * Exact geboekt is, vervangt de job hem door een versie met het
         * officiële Exact-factuurnummer.
         */
        $this->invoiceService->generatePdf($invoice->fresh());

        ProcessCashPaidInvoice::dispatch($invoice->fresh());

        return $invoice->fresh();
    }
}
