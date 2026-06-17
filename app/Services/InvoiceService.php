<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Support\UploadStorage;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    public function __construct(
        private InvoiceLineCalculator $lineCalculator,
        private InvoiceUblBuilder $ublBuilder,
    ) {}

    public function createFromDelivery(Delivery $delivery): Invoice
    {
        $delivery->load('items');
        $order = $delivery->order->load('items.product', 'customer');

        if ($order->invoice) {
            return $this->recalculateInvoice($order->invoice, $delivery, $order);
        }

        $amounts = $this->calculateAmountsFromDelivery($delivery, $order);

        return Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->generateInvoiceNumber(),
            'status' => InvoiceStatus::CONCEPT,
            'invoice_date' => now(),
            'due_date' => now()->addDays(14),
            'subtotal_amount' => $amounts['subtotal'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
        ]);
    }

    protected function recalculateInvoice(Invoice $invoice, Delivery $delivery, $order): Invoice
    {
        $amounts = $this->calculateAmountsFromDelivery($delivery, $order);

        $invoice->update([
            'subtotal_amount' => $amounts['subtotal'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
        ]);

        return $invoice->fresh();
    }

    /**
     * @return array{subtotal: float, vat: float, total: float}
     */
    public function calculateAmountsFromDelivery(Delivery $delivery, $order): array
    {
        $totals = $this->lineCalculator->totals($order, $delivery);

        return [
            'subtotal' => $totals['subtotal'],
            'vat' => $totals['vat'],
            'total' => $totals['total'],
        ];
    }

    public function generatePdf(Invoice $invoice): void
    {
        $invoice->load([
            'order.customer',
            'order.items.product',
            'order.delivery.items',
        ]);

        $lines = $this->lineCalculator->lines($invoice->order, $invoice->order->delivery);
        $totals = $this->lineCalculator->totals($invoice->order, $invoice->order->delivery);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'lines' => $lines,
            'vatByRate' => $totals['vat_by_rate'],
        ])
            ->setPaper('a4', 'portrait');

        $path = UploadStorage::directory('invoices/pdf')."/{$invoice->invoice_number}.pdf";
        UploadStorage::disk()->put($path, $pdf->output());

        $invoice->update(['pdf_path' => $path]);
    }

    public function generateUbl(Invoice $invoice): void
    {
        $invoice->load([
            'order.customer',
            'order.items.product',
            'order.delivery.items',
        ]);

        $order = $invoice->order;
        $lines = $this->lineCalculator->lines($order, $order->delivery);
        $totals = $this->lineCalculator->totals($order, $order->delivery);

        $xml = $this->ublBuilder->build($invoice, $lines, $totals['vat_by_rate']);

        $path = UploadStorage::directory('invoices/ubl')."/{$invoice->invoice_number}.xml";
        UploadStorage::disk()->put($path, $xml);
        $invoice->update(['ubl_path' => $path]);
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $last = Invoice::whereYear('created_at', $year)->max('id') ?? 0;
        $number = str_pad($last + 1, 4, '0', STR_PAD_LEFT);

        return "F{$year}-{$number}";
    }
}
