<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InvoiceService;

/**
 * @return array{invoice: Invoice, item: OrderItem}
 */
function seedInvoiceScenario(): array
{
    $customer = Customer::factory()->create(['is_vat_exempt' => false]);
    $product = Product::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'box_weight_kg' => 2,
        'price_per_kg' => 10,
        'vat_rate' => 21,
    ]);

    $delivery = Delivery::factory()->create(['order_id' => $order->id]);
    DeliveryItem::factory()->create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'ordered_quantity' => 5,
        'delivered_quantity' => 5,
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    // 5 dozen × 2 kg × € 10/kg = € 100 + 21% btw = € 121.
    expect((float) $invoice->total_amount)->toBe(121.0);

    return ['invoice' => $invoice, 'item' => $item];
}

it('herberekent de conceptfactuur wanneer prijs per kg wijzigt', function () {
    ['invoice' => $invoice, 'item' => $item] = seedInvoiceScenario();

    $item->update(['price_per_kg' => 20]);

    $invoice->refresh();

    // 5 × 2 × € 20 = € 200 + 21% = € 242.
    expect((float) $invoice->subtotal_amount)->toBe(200.0);
    expect((float) $invoice->total_amount)->toBe(242.0);
});

it('laat een verzonden factuur ongemoeid bij een prijswijziging', function () {
    ['invoice' => $invoice, 'item' => $item] = seedInvoiceScenario();

    $invoice->update(['status' => InvoiceStatus::SENT]);
    $originalTotal = (float) $invoice->total_amount;

    $item->update(['price_per_kg' => 20]);

    $invoice->refresh();

    expect((float) $invoice->total_amount)->toBe($originalTotal);
});
