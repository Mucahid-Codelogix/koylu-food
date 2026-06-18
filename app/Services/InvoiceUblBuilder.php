<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Collection;

class InvoiceUblBuilder
{
    /**
     * @param  Collection<int, array{
     *     product_name: string,
     *     delivered_kg: float,
     *     price_per_kg: float,
     *     line_subtotal: float,
     *     vat_rate: float,
     *     line_vat: float,
     * }>  $lines
     * @param  array<int, array{rate: float, taxable_amount: float, vat_amount: float}>  $vatByRate
     */
    public function build(Invoice $invoice, Collection $lines, array $vatByRate): string
    {
        $invoice->loadMissing([
            'order.customer',
        ]);

        $order = $invoice->order;
        $customer = $order->customer;

        $invoiceLines = $lines
            ->values()
            ->map(fn (array $line, int $index): string => $this->invoiceLineXml($index + 1, $line))
            ->implode('');

        $taxSubtotals = collect($vatByRate)
            ->map(fn (array $group): string => $this->taxSubtotalXml($group))
            ->implode('');

        $vatNumber = $customer->vat_number
            ? '<cac:PartyTaxScheme>
                <cbc:CompanyID>'.$this->escape($customer->vat_number).'</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>'
            : '';

        return '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">

    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>
    <cbc:ID>'.$this->escape($invoice->displayInvoiceNumber()).'</cbc:ID>
    <cbc:IssueDate>'.$invoice->invoice_date?->format('Y-m-d').'</cbc:IssueDate>
    <cbc:DueDate>'.$invoice->due_date?->format('Y-m-d').'</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
    <cbc:BuyerReference>'.$this->escape($order->order_number).'</cbc:BuyerReference>

    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>'.$this->escape(config('brand.name')).'</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cac:Country><cbc:IdentificationCode>NL</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>NL XXXXXXXXX B01</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>'.$this->escape(config('brand.name')).'</cbc:RegistrationName>
                <cbc:CompanyID>XXXXXXXX</cbc:CompanyID>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>'.$this->escape($customer->company_name).'</cbc:Name></cac:PartyName>
            <cac:PostalAddress>
                <cbc:StreetName>'.$this->escape($customer->address).'</cbc:StreetName>
                <cbc:CityName>'.$this->escape($customer->city).'</cbc:CityName>
                <cbc:PostalZone>'.$this->escape($customer->postal_code).'</cbc:PostalZone>
                <cac:Country><cbc:IdentificationCode>'.$this->escape($customer->country).'</cbc:IdentificationCode></cac:Country>
            </cac:PostalAddress>
            '.$vatNumber.'
            <cac:PartyLegalEntity>
                <cbc:RegistrationName>'.$this->escape($customer->company_name).'</cbc:RegistrationName>
            </cac:PartyLegalEntity>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">'.$this->money($invoice->vat_amount).'</cbc:TaxAmount>
        '.$taxSubtotals.'
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">'.$this->money($invoice->subtotal_amount).'</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">'.$this->money($invoice->subtotal_amount).'</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">'.$this->money($invoice->total_amount).'</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EUR">'.$this->money($invoice->total_amount).'</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    '.$invoiceLines.'
</Invoice>';
    }

    /**
     * @param  array{
     *     product_name: string,
     *     delivered_kg: float,
     *     price_per_kg: float,
     *     line_subtotal: float,
     *     vat_rate: float,
     *     line_vat: float,
     * }  $line
     */
    protected function invoiceLineXml(int $lineId, array $line): string
    {
        $vatCode = $this->ublVatCode($line['vat_rate']);
        $productName = $this->escape($line['product_name']);

        return '
    <cac:InvoiceLine>
        <cbc:ID>'.$lineId.'</cbc:ID>
        <cbc:InvoicedQuantity unitCode="KGM">'.$this->quantity($line['delivered_kg']).'</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="EUR">'.$this->money($line['line_subtotal']).'</cbc:LineExtensionAmount>
        <cac:TaxTotal>
            <cbc:TaxAmount currencyID="EUR">'.$this->money($line['line_vat']).'</cbc:TaxAmount>
        </cac:TaxTotal>
        <cac:Item>
            <cbc:Description>'.$productName.'</cbc:Description>
            <cbc:Name>'.$productName.'</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>'.$vatCode.'</cbc:ID>
                <cbc:Percent>'.$this->percent($line['vat_rate']).'</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="EUR">'.$this->pricePerKg($line['price_per_kg']).'</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>';
    }

    /**
     * @param  array{rate: float, taxable_amount: float, vat_amount: float}  $group
     */
    protected function taxSubtotalXml(array $group): string
    {
        $vatCode = $this->ublVatCode($group['rate']);

        return '
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="EUR">'.$this->money($group['taxable_amount']).'</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="EUR">'.$this->money($group['vat_amount']).'</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>'.$vatCode.'</cbc:ID>
                <cbc:Percent>'.$this->percent($group['rate']).'</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>';
    }

    public function ublVatCode(float $vatRate): string
    {
        return match (true) {
            $vatRate == 0.0 => 'Z',
            $vatRate == 9.0 => 'AA',
            default => 'S',
        };
    }

    protected function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function pricePerKg(float $amount): string
    {
        return number_format($amount, 4, '.', '');
    }

    protected function quantity(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 3, '.', ''), '0'), '.');
    }

    protected function percent(float $rate): string
    {
        return number_format($rate, fmod($rate, 1.0) === 0.0 ? 0 : 2, '.', '');
    }

    protected function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
