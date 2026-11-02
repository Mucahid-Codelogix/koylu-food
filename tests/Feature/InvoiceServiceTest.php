<?php

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates invoice amounts from delivered quantities', function () {
    $customer = Customer::factory()->create(['is_vat_exempt' => true]);
    $order = Order::factory()->for($customer)->create(['total_price' => 200]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'quantity' => 2,
        'box_weight_kg' => 5,
        'price_per_kg' => 10,
        'vat_rate' => 21,
        'unit_price' => 50,
        'subtotal' => 100,
    ]);

    $delivery = Delivery::factory()->create([
        'order_id' => $order->id,
        'status' => DeliveryStatus::PARTIAL,
    ]);

    DeliveryItem::query()->create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 1,
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    expect((float) $invoice->subtotal_amount)->toBe(50.0)
        ->and((float) $invoice->vat_amount)->toBe(0.0)
        ->and((float) $invoice->total_amount)->toBe(50.0);
});

function invoiceForNewDelivery(): Invoice
{
    $delivery = Delivery::factory()->create();

    return app(InvoiceService::class)->createFromDelivery($delivery);
}

it('begint het factuurnummer bij 0001 nadat alle facturen gewist zijn, ook als het id doortelt', function () {
    $this->travelTo('2026-11-02');

    collect(range(1, 3))->each(fn () => invoiceForNewDelivery());
    Invoice::query()->delete();

    expect(invoiceForNewDelivery()->invoice_number)->toBe('F2026-0001')
        ->and(invoiceForNewDelivery()->invoice_number)->toBe('F2026-0002');
});

it('begint het factuurnummer in een nieuw jaar bij 0001 en telt daarna door', function () {
    $this->travelTo('2026-12-31');
    collect(range(1, 3))->each(fn () => invoiceForNewDelivery());

    $this->travelTo('2027-01-01');

    expect(invoiceForNewDelivery()->invoice_number)->toBe('F2027-0001')
        ->and(invoiceForNewDelivery()->invoice_number)->toBe('F2027-0002');
});
