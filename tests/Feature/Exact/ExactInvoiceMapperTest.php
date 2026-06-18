<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Exact\ExactInvoiceMapper;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps billable invoice lines to exact sales invoice lines in kg', function () {
    config([
        'exact.invoice.journal' => '70',
        'exact.item.unit' => 'kg',
        'exact.invoice.vat_codes.21.00' => 'VH',
        'exact.invoice.vat_codes.9.00' => 'VL',
    ]);

    $customer = Customer::factory()->create([
        'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
    ]);

    ['order' => $order, 'delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 2, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
        ['delivered' => 1, 'box_weight_kg' => 4, 'price_per_kg' => 8, 'vat_rate' => 9],
        ['delivered' => 0, 'box_weight_kg' => 3, 'price_per_kg' => 7, 'vat_rate' => 21, 'missed_reason' => 'Niet geleverd'],
    ]);

    $order->update(['customer_id' => $customer->id]);
    $order->load(['items.product', 'customer']);

    $itemIds = [];

    foreach ($order->items as $item) {
        $item->product->update(['exact_article_code' => 'ART-'.$item->product_id]);
        $itemIds[$item->product_id] = 'item-'.$item->product_id;
    }

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    $payload = ExactInvoiceMapper::toExactSalesInvoice($invoice->fresh([
        'order.customer',
        'order.items.product',
        'order.delivery.items',
    ]), $itemIds);

    expect($payload['OrderedBy'])->toBe($customer->exact_account_id)
        ->and($payload['Journal'])->toBe('70')
        ->and($payload['SalesInvoiceLines'])->toHaveCount(2)
        ->and($payload['SalesInvoiceLines'][0])->toMatchArray([
            'Quantity' => 10.0,
            'UnitPrice' => 10.0,
            'UnitCode' => 'kg',
            'VATCode' => 'VH',
        ])
        ->and($payload['SalesInvoiceLines'][1])->toMatchArray([
            'Quantity' => 4.0,
            'UnitPrice' => 8.0,
            'VATCode' => 'VL',
        ]);
});

it('maps vat exempt lines to the configured zero vat code', function () {
    config(['exact.invoice.vat_codes.0.00' => 'V0']);

    $customer = Customer::factory()->create([
        'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'is_vat_exempt' => true,
    ]);

    ['order' => $order, 'delivery' => $delivery] = makeInvoiceOrder([
        ['delivered' => 1, 'box_weight_kg' => 5, 'price_per_kg' => 10, 'vat_rate' => 21],
    ], vatExempt: true);

    $order->update(['customer_id' => $customer->id]);
    $order->load(['items.product', 'customer']);

    $item = $order->items->first();
    $item->product->update(['exact_article_code' => 'ART-1']);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    $payload = ExactInvoiceMapper::toExactSalesInvoice($invoice->fresh([
        'order.customer',
        'order.items.product',
        'order.delivery.items',
    ]), [
        $item->product_id => 'item-1',
    ]);

    expect($payload['SalesInvoiceLines'][0]['VATCode'])->toBe('V0');
});

it('uses the exact document number on the invoice display number when present', function () {
    $invoice = Invoice::factory()->make([
        'invoice_number' => 'F2026-0001',
        'exact_document_number' => '20260042',
    ]);

    expect($invoice->displayInvoiceNumber())->toBe('20260042');
});

it('falls back to the internal invoice number when exact number is missing', function () {
    $invoice = Invoice::factory()->make([
        'invoice_number' => 'F2026-0001',
        'exact_document_number' => null,
    ]);

    expect($invoice->displayInvoiceNumber())->toBe('F2026-0001');
});
