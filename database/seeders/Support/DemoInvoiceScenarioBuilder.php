<?php

namespace Database\Seeders\Support;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InvoiceService;

class DemoInvoiceScenarioBuilder
{
    /**
     * @param  array<int, array{
     *     product_name: string,
     *     unit?: string,
     *     quantity?: float,
     *     box_weight_kg: float,
     *     price_per_kg: float,
     *     vat_rate: float,
     *     delivered: float,
     *     missed_reason?: ?string,
     * }>  $lineSpecs
     */
    public function create(
        Customer $customer,
        string $orderNumber,
        string $notes,
        array $lineSpecs,
        DeliveryStatus $deliveryStatus = DeliveryStatus::PARTIAL,
    ): Invoice {
        $this->removeExistingScenario($orderNumber);

        $product = $this->demoProduct();

        $order = Order::create([
            'order_number' => $orderNumber,
            'customer_id' => $customer->id,
            'status' => OrderStatus::DELIVERED,
            'order_date' => now()->subDay(),
            'delivery_date' => now()->subDay(),
            'total_price' => 0,
            'notes' => $notes,
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'delivered_at' => now()->subDay(),
            'receiver_name' => $customer->contact_name ?? $customer->company_name,
            'status' => $deliveryStatus,
        ]);

        foreach ($lineSpecs as $index => $spec) {
            $quantity = $spec['quantity'] ?? 1;
            $boxWeightKg = $spec['box_weight_kg'];
            $pricePerKg = $spec['price_per_kg'];

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $spec['product_name'],
                'unit' => $spec['unit'] ?? 'Doos demo',
                'quantity' => $quantity,
                'box_weight_kg' => $boxWeightKg,
                'price_per_kg' => $pricePerKg,
                'vat_rate' => $spec['vat_rate'],
                'unit_price' => $boxWeightKg * $pricePerKg,
                'subtotal' => $quantity * $boxWeightKg * $pricePerKg,
            ]);

            DeliveryItem::query()->create([
                'delivery_id' => $delivery->id,
                'order_item_id' => $item->id,
                'product_id' => $product->id,
                'ordered_quantity' => $quantity,
                'delivered_quantity' => $spec['delivered'],
                'missed_reason' => $spec['delivered'] == 0 ? ($spec['missed_reason'] ?? 'Niet geleverd') : null,
            ]);
        }

        $order->update(['total_price' => $order->items()->sum('subtotal')]);

        return app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));
    }

    protected function removeExistingScenario(string $orderNumber): void
    {
        $order = Order::query()->where('order_number', $orderNumber)->first();

        if (! $order) {
            return;
        }

        $order->invoice?->delete();
        $order->delivery?->items()->delete();
        $order->delivery?->delete();
        $order->items()->delete();
        $order->delete();
    }

    protected function demoProduct(): Product
    {
        return Product::query()->firstOrCreate(
            ['name' => 'Factuur demo artikel'],
            [
                'description' => 'Intern demo-artikel voor factuurscenario’s in de admin',
                'product_type' => ProductType::Standard,
                'min_order_quantity' => 1,
                'is_active' => false,
                'vat_category' => VatCategory::High,
            ],
        );
    }
}
