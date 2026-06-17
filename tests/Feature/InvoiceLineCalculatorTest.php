<?php

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceLineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{order: Order, delivery: Delivery}
 */
function makeCalculatorOrder(array $lineSpecs, bool $vatExempt = false): array
{
    $customer = Customer::factory()->create(['is_vat_exempt' => $vatExempt]);
    $order = Order::factory()->for($customer)->create();
    $delivery = Delivery::factory()->create(['order_id' => $order->id]);

    foreach ($lineSpecs as $spec) {
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => $spec['quantity'] ?? 1,
            'box_weight_kg' => $spec['box_weight_kg'],
            'price_per_kg' => $spec['price_per_kg'],
            'vat_rate' => $spec['vat_rate'] ?? 21,
            'unit_price' => $spec['unit_price'] ?? 0,
            'subtotal' => 0,
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'ordered_quantity' => $spec['quantity'] ?? 1,
            'delivered_quantity' => $spec['delivered_quantity'],
            'missed_reason' => ($spec['delivered_quantity'] ?? 0) == 0 ? ($spec['missed_reason'] ?? null) : null,
        ]);
    }

    return [
        'order' => $order->fresh(['items', 'customer']),
        'delivery' => $delivery->fresh('items'),
    ];
}

it('calculates line subtotals from delivered kg times price per kg', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'quantity' => 4,
            'delivered_quantity' => 4,
            'box_weight_kg' => 2.5,
            'price_per_kg' => 5.02,
            'vat_rate' => 21,
        ],
        [
            'quantity' => 3,
            'delivered_quantity' => 1.5,
            'box_weight_kg' => 2,
            'price_per_kg' => 4.90,
            'vat_rate' => 21,
        ],
        [
            'quantity' => 2,
            'delivered_quantity' => 0,
            'box_weight_kg' => 3,
            'price_per_kg' => 7.25,
            'vat_rate' => 21,
            'missed_reason' => 'Niet op voorraad',
        ],
    ]);

    $lines = app(InvoiceLineCalculator::class)->lines($order, $delivery);

    expect($lines[0]['delivered_kg'])->toBe(10.0)
        ->and($lines[0]['line_subtotal'])->toBe(50.20)
        ->and($lines[0]['vat_rate'])->toBe(21.0)
        ->and($lines[0]['line_vat'])->toBe(10.54)
        ->and($lines[1]['delivered_kg'])->toBe(3.0)
        ->and($lines[1]['line_subtotal'])->toBe(14.70)
        ->and($lines[1]['line_vat'])->toBe(3.09)
        ->and($lines[2]['delivered_kg'])->toBe(0.0)
        ->and($lines[2]['line_subtotal'])->toBe(0.0)
        ->and($lines[2]['line_vat'])->toBe(0.0)
        ->and($lines[2]['is_missed'])->toBeTrue();
});

it('sums line vat amounts into invoice totals', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 4,
            'box_weight_kg' => 2.5,
            'price_per_kg' => 5.02,
            'vat_rate' => 21,
        ],
        [
            'delivered_quantity' => 1.5,
            'box_weight_kg' => 2,
            'price_per_kg' => 4.90,
            'vat_rate' => 21,
        ],
    ]);

    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($totals['subtotal'])->toBe(64.90)
        ->and($totals['vat'])->toBe(13.63)
        ->and($totals['total'])->toBe(78.53);
});

it('applies per line vat rates from the order item snapshot', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 9,
        ],
        [
            'delivered_quantity' => 1,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ]);

    $lines = app(InvoiceLineCalculator::class)->lines($order, $delivery);
    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($lines[0]['line_subtotal'])->toBe(100.0)
        ->and($lines[0]['vat_rate'])->toBe(9.0)
        ->and($lines[0]['line_vat'])->toBe(9.0)
        ->and($lines[1]['line_subtotal'])->toBe(50.0)
        ->and($lines[1]['vat_rate'])->toBe(21.0)
        ->and($lines[1]['line_vat'])->toBe(10.50)
        ->and($totals['subtotal'])->toBe(150.0)
        ->and($totals['vat'])->toBe(19.50)
        ->and($totals['total'])->toBe(169.50)
        ->and($totals['vat_by_rate'])->toBe([
            ['rate' => 9.0, 'taxable_amount' => 100.0, 'vat_amount' => 9.0],
            ['rate' => 21.0, 'taxable_amount' => 50.0, 'vat_amount' => 10.50],
        ]);
});

it('groups vat breakdown by rate for single rate invoices', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 4,
            'box_weight_kg' => 2.5,
            'price_per_kg' => 5.02,
            'vat_rate' => 21,
        ],
        [
            'delivered_quantity' => 1.5,
            'box_weight_kg' => 2,
            'price_per_kg' => 4.90,
            'vat_rate' => 21,
        ],
    ]);

    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($totals['vat_by_rate'])->toBe([
        ['rate' => 21.0, 'taxable_amount' => 64.90, 'vat_amount' => 13.63],
    ]);
});

it('groups all lines under zero percent for vat exempt customers', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ], vatExempt: true);

    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($totals['vat_by_rate'])->toBe([
        ['rate' => 0.0, 'taxable_amount' => 100.0, 'vat_amount' => 0.0],
    ]);
});

it('omits zero amount vat rate groups from the breakdown', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
        [
            'delivered_quantity' => 0,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 9,
            'missed_reason' => 'Niet geleverd',
        ],
    ]);

    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($totals['vat_by_rate'])->toBe([
        ['rate' => 21.0, 'taxable_amount' => 100.0, 'vat_amount' => 21.0],
    ]);
});

it('forces zero vat on every line for vat exempt customers', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ], vatExempt: true);

    $lines = app(InvoiceLineCalculator::class)->lines($order, $delivery);
    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    expect($lines[0]['vat_rate'])->toBe(0.0)
        ->and($lines[0]['line_vat'])->toBe(0.0)
        ->and($totals['vat'])->toBe(0.0)
        ->and($totals['total'])->toBe($totals['subtotal']);
});

it('bills zero for missing delivery lines', function () {
    ['order' => $order, 'delivery' => $delivery] = makeCalculatorOrder([
        [
            'delivered_quantity' => 0,
            'box_weight_kg' => 10,
            'price_per_kg' => 12.55,
            'vat_rate' => 21,
            'missed_reason' => 'Niet geleverd',
        ],
    ]);

    $lines = app(InvoiceLineCalculator::class)->lines($order, $delivery);

    expect($lines[0]['delivered_kg'])->toBe(0.0)
        ->and($lines[0]['line_subtotal'])->toBe(0.0)
        ->and($lines[0]['line_vat'])->toBe(0.0);
});
