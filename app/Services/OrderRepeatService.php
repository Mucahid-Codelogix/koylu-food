<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRepeatService
{
    public function __construct(
        protected OrderItemSnapshotBuilder $snapshotBuilder,
    ) {}

    /**
     * Zoekt de nog beschikbare productkeuzes van een eerdere orderregel.
     *
     * @return array{product: Product, product_supplier: ProductSupplier, packaging: ?ProductPackaging, gram_variant: ?ProductGramVariant, quantity: float}|null
     */
    public function resolveAvailableLine(OrderItem $item): ?array
    {
        $product = Product::query()
            ->where('is_active', true)
            ->with(['activePackagings.productSuppliers', 'activeGramVariants.productSuppliers', 'activeProductSuppliers.supplier'])
            ->find($item->product_id);

        if (! $product) {
            return null;
        }

        $productSupplier = $item->product_supplier_id
            ? $product->activeProductSuppliers->firstWhere('id', $item->product_supplier_id)
            : $product->defaultProductSupplier();

        if (! $productSupplier) {
            return null;
        }

        if ($product->isWholeChicken()) {
            $availableVariants = $product->activeGramVariants
                ->filter(fn (ProductGramVariant $variant): bool => $variant->isAvailableForProductSupplier($productSupplier->id))
                ->values();

            $gramVariant = $availableVariants->firstWhere('id', $item->product_gram_variant_id)
                ?? $availableVariants->firstWhere('is_default', true)
                ?? $availableVariants->first();

            if (! $gramVariant) {
                return null;
            }

            return [
                'product' => $product,
                'product_supplier' => $productSupplier,
                'packaging' => null,
                'gram_variant' => $gramVariant,
                'quantity' => max((float) ($product->min_order_quantity ?? 1), (float) $item->quantity),
            ];
        }

        $packaging = $product->activePackagings->firstWhere('id', $item->product_packaging_id)
            ?? $product->defaultPackaging();

        if (! $packaging || ! $packaging->isAvailableForProductSupplier($productSupplier->id)) {
            return null;
        }

        return [
            'product' => $product,
            'product_supplier' => $productSupplier,
            'packaging' => $packaging,
            'gram_variant' => null,
            'quantity' => max((float) ($packaging->min_order_quantity ?? $product->min_order_quantity ?? 1), (float) $item->quantity),
        ];
    }

    /**
     * Plaatst een kopie van een eerdere bestelling tegen de actuele prijzen.
     *
     * @return array{order: ?Order, skipped: array<int, string>}
     */
    public function placeCopy(Order $order): array
    {
        $order->loadMissing('items');

        $skipped = [];
        $snapshots = [];

        foreach ($order->items as $item) {
            $line = $this->resolveAvailableLine($item);

            if (! $line) {
                $skipped[] = $item->product_name;

                continue;
            }

            $snapshots[] = $line['gram_variant']
                ? $this->snapshotBuilder->fromWholeChickenLine(
                    $line['product'],
                    $line['gram_variant'],
                    $line['product_supplier'],
                    $line['quantity'],
                    $order->customer,
                )
                : $this->snapshotBuilder->fromStandardLine(
                    $line['product'],
                    $line['packaging'],
                    $line['product_supplier'],
                    $line['quantity'],
                    $order->customer,
                );
        }

        if ($snapshots === []) {
            return ['order' => null, 'skipped' => array_values(array_unique($skipped))];
        }

        $newOrder = DB::transaction(function () use ($order, $snapshots): Order {
            $newOrder = Order::create([
                'order_number' => 'ORD-'.strtoupper(Str::random(6)),
                'customer_id' => $order->customer_id,
                'status' => OrderStatus::PLACED,
                'order_date' => now(),
                'delivery_date' => now()->addDay(),
                'total_price' => collect($snapshots)->sum('subtotal'),
            ]);

            foreach ($snapshots as $attributes) {
                OrderItem::create(array_merge(['order_id' => $newOrder->id], $attributes));
            }

            return $newOrder;
        });

        return ['order' => $newOrder, 'skipped' => array_values(array_unique($skipped))];
    }
}
