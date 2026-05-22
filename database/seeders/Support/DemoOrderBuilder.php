<?php

namespace Database\Seeders\Support;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Services\OrderItemSnapshotBuilder;
use Illuminate\Support\Str;

class DemoOrderBuilder
{
    public function __construct(
        protected OrderItemSnapshotBuilder $snapshotBuilder,
    ) {}

    public function createPlacedOrder(
        Customer $customer,
        string $orderNumber,
        ?\DateTimeInterface $deliveryDate = null,
        ?string $notes = null,
    ): Order {
        return Order::create([
            'order_number' => $orderNumber,
            'customer_id' => $customer->id,
            'status' => OrderStatus::PLACED,
            'order_date' => now(),
            'delivery_date' => $deliveryDate ?? now()->addDay(),
            'total_price' => 0,
            'notes' => $notes,
        ]);
    }

    public function addStandardLine(
        Order $order,
        Product $product,
        Customer $customer,
        float $quantity,
        ?ProductPackaging $packaging = null,
        ?ProductSupplier $productSupplier = null,
    ): OrderItem {
        $packaging = $packaging ?? $product->defaultPackaging();
        $productSupplier = $productSupplier ?? $product->defaultProductSupplier();

        $attributes = $this->snapshotBuilder->fromStandardLine(
            $product,
            $packaging,
            $productSupplier,
            $quantity,
            $customer,
        );

        return $this->createItem($order, $attributes);
    }

    public function addWholeChickenLine(
        Order $order,
        Product $product,
        Customer $customer,
        float $boxQuantity,
        ?ProductGramVariant $gramVariant = null,
        ?ProductSupplier $productSupplier = null,
    ): OrderItem {
        $gramVariant = $gramVariant ?? $product->defaultGramVariant();
        $productSupplier = $productSupplier ?? $product->defaultProductSupplier();

        $attributes = $this->snapshotBuilder->fromWholeChickenLine(
            $product,
            $gramVariant,
            $productSupplier,
            $boxQuantity,
            $customer,
        );

        return $this->createItem($order, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createItem(Order $order, array $attributes): OrderItem
    {
        return OrderItem::create(array_merge(['order_id' => $order->id], $attributes));
    }

    public function recalculateTotal(Order $order): Order
    {
        $total = $order->items()->sum('subtotal');

        $order->update(['total_price' => $total]);

        return $order->fresh();
    }

    public static function uniqueOrderNumber(string $prefix): string
    {
        return strtoupper($prefix).'-'.Str::upper(Str::random(6));
    }
}
