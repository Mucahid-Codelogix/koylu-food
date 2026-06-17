<?php

use App\Models\Delivery;
use App\Models\Order;
use App\Services\InvoiceLineCalculator;
use App\Services\InvoiceService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Partial delivery scenario with three lines (full, partial, missed).
 *
 * @return array{order: Order, delivery: Delivery}
 */
function makeDeliveredOrder(bool $vatExempt = false): array
{
    return makeInvoiceOrder([
        ['quantity' => 4, 'box_weight_kg' => 2.5, 'price_per_kg' => 5.02, 'vat_rate' => 21, 'delivered' => 4],
        ['quantity' => 3, 'box_weight_kg' => 2, 'price_per_kg' => 4.90, 'vat_rate' => 21, 'delivered' => 1.5],
        ['quantity' => 2, 'box_weight_kg' => 3, 'price_per_kg' => 7.25, 'vat_rate' => 21, 'delivered' => 0, 'missed_reason' => 'Niet op voorraad'],
    ], $vatExempt);
}

it('produces identical totals across the calculator, the invoice record, the PDF and the UBL', function () {
    Storage::fake(UploadStorage::diskName());
    ['order' => $order, 'delivery' => $delivery] = makeDeliveredOrder();

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    // Expected: 10*5.02 + 3*4.90 + 0 = 50.20 + 14.70 = 64.90
    expect((float) $invoice->subtotal_amount)->toBe(64.90)
        ->and((float) $invoice->vat_amount)->toBe(13.63)
        ->and((float) $invoice->total_amount)->toBe(78.53);

    assertInvoiceConsistencyAcrossFormats($order, $delivery, $invoice);
});

it('keeps record pdf and ubl in sync for mixed vat rates', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 2, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 9],
        ['delivered' => 1, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    expect((float) $invoice->subtotal_amount)->toBe(150.0)
        ->and((float) $invoice->vat_amount)->toBe(19.50)
        ->and((float) $invoice->total_amount)->toBe(169.50);

    assertInvoiceConsistencyAcrossFormats($order, $delivery, $invoice);
});

it('keeps record pdf and ubl in sync for vat exempt customers', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeDeliveredOrder(vatExempt: true);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    expect((float) $invoice->vat_amount)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe((float) $invoice->subtotal_amount);

    assertInvoiceConsistencyAcrossFormats($order, $delivery, $invoice);
});

it('keeps record pdf and ubl in sync for partial deliveries', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeDeliveredOrder();

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    assertInvoiceConsistencyAcrossFormats($order, $delivery, $invoice);

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->ubl_path);
    $quantities = collect(ublDocument($ubl)->xpath('//cac:InvoiceLine/cbc:InvoicedQuantity'))
        ->map(fn (SimpleXMLElement $node): float => (float) $node)
        ->all();

    expect($quantities)->toBe([10.0, 3.0, 0.0])
        ->and(sumUblLineTotals($ubl))->toBe(64.90);
});

it('matches calculator line amounts to ubl invoice lines to the cent', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeDeliveredOrder();
    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);
    app(InvoiceService::class)->generateUbl($invoice->refresh());

    $calculatorLines = app(InvoiceLineCalculator::class)->lines($order, $delivery);
    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->refresh()->ubl_path);
    $ublLines = ublDocument($ubl)->xpath('//cac:InvoiceLine');

    expect($ublLines)->toHaveCount($calculatorLines->count());

    foreach ($calculatorLines as $index => $line) {
        $ublLine = $ublLines[$index];

        expect((float) $ublLine->xpath('cbc:InvoicedQuantity')[0])->toBe($line['delivered_kg'])
            ->and((float) $ublLine->xpath('cbc:LineExtensionAmount')[0])->toBe($line['line_subtotal'])
            ->and((float) $ublLine->xpath('cac:TaxTotal/cbc:TaxAmount')[0])->toBe($line['line_vat'])
            ->and((float) $ublLine->xpath('cac:Price/cbc:PriceAmount')[0])->toBe($line['price_per_kg']);
    }
});
