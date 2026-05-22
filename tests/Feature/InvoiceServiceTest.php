<?php

use App\Enums\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
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
