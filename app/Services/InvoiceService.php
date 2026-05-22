<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Delivery;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
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
        $subtotal = 0.0;

        foreach ($order->items as $item) {
            $deliveryItem = $delivery->items->firstWhere('order_item_id', $item->id);
            $deliveredQty = (float) ($deliveryItem?->delivered_quantity ?? 0);
            $subtotal += round($deliveredQty * (float) $item->unit_price, 2);
        }

        $vatRate = $order->customer->is_vat_exempt ? 0 : 0.21;
        $vat = round($subtotal * $vatRate, 2);
        $total = round($subtotal + $vat, 2);

        return [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
        ];
    }

    public function generatePdf(Invoice $invoice): void
    {
        $invoice->load([
            'order.customer',
            'order.items.product',
            'order.delivery.items',
        ]);

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'))
            ->setPaper('a4', 'portrait');

        $path = "invoices/pdf/{$invoice->invoice_number}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

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
        $customer = $order->customer;
        $vatRate = $customer->is_vat_exempt ? 0 : 21;
        $vatCode = $customer->is_vat_exempt ? 'Z' : 'S';

        $lines = '';
        foreach ($order->items as $index => $item) {
            $deliveryItem = $order->delivery?->items->firstWhere('order_item_id', $item->id);
            $deliveredQty = $deliveryItem?->delivered_quantity ?? $item->quantity;
            $lineTotal = round((float) $deliveredQty * (float) $item->unit_price, 2);
            $lineTax = round($lineTotal * ($vatRate / 100), 2);

            $lines .= '
    <cac:InvoiceLine>
        <cbc:ID>'.($index + 1).'</cbc:ID>
        <cbc:InvoicedQuantity unitCode="'.strtoupper($item->unit)."\">{$deliveredQty}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID=\"EUR\">".number_format($lineTotal, 2, '.', '').'</cbc:LineExtensionAmount>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="EUR">'.number_format($lineTax, 2, '.', '')."</cbc:TaxAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Description>{$item->product_name}</cbc:Description>
            <cbc:Name>{$item->product_name}</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>{$vatCode}</cbc:ID>
                <cbc:Percent>{$vatRate}</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID=\"EUR\">".number_format($item->unit_price, 2, '.', '').'</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>';
        }

        $vatNumber = $customer->vat_number
            ? "<cac:PartyTaxScheme>
                <cbc:CompanyID>{$customer->vat_number}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>"
            : '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">

    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>
    <cbc:ID>'.$invoice->invoice_number.'</cbc:ID>
    <cbc:IssueDate>'.$invoice->invoice_date?->format('Y-m-d').'</cbc:IssueDate>
    <cbc:DueDate>'.$invoice->due_date?->format('Y-m-d').'</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>'.$order->order_number.'</cbc:BuyerReference>

    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>'.e(config('brand.name')).'</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cac:Country><cbc:IdentificationCode>NL</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>NL XXXXXXXXX B01</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>'.e(config('brand.name')).'</cbc:RegistrationName>
                <cbc:CompanyID>XXXXXXXX</cbc:CompanyID>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>'.e($customer->company_name).'</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>'.e($customer->address).'</cbc:StreetName>
                <cbc:CityName>'.e($customer->city).'</cbc:CityName>
                <cbc:PostalZone>'.e($customer->postal_code).'</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>'.e($customer->country).'</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            '.$vatNumber.'
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>'.e($customer->company_name).'</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">'.number_format($invoice->vat_amount, 2, '.', '').'</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">'.number_format($invoice->subtotal_amount, 2, '.', '').'</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">'.number_format($invoice->vat_amount, 2, '.', '').'</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>'.$vatCode.'</cbc:ID>
                <cbc:Percent>'.$vatRate.'</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">'.number_format($invoice->subtotal_amount, 2, '.', '').'</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">'.number_format($invoice->subtotal_amount, 2, '.', '').'</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">'.number_format($invoice->total_amount, 2, '.', '').'</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">'.number_format($invoice->total_amount, 2, '.', '').'</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    '.$lines.'
</Invoice>';

        $path = "invoices/ubl/{$invoice->invoice_number}.xml";
        Storage::disk('public')->put($path, $xml);
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
