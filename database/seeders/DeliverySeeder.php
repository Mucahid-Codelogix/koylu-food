<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\RouteStopStatus;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\RouteStop;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    /**
     * Vult ontbrekende leveringen aan voor stops die al als geleverd staan
     * (RouteSeeder maakt de meeste demo-leveringen al aan).
     */
    public function run(): void
    {
        RouteStop::query()
            ->where('status', RouteStopStatus::DELIVERED)
            ->with(['order.items', 'order.customer'])
            ->get()
            ->each(function (RouteStop $stop): void {
                $order = $stop->order;

                if (Delivery::query()->where('order_id', $order->id)->exists()) {
                    return;
                }

                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'delivered_at' => now(),
                    'receiver_name' => $order->customer->contact_name ?? $order->customer->company_name,
                    'status' => DeliveryStatus::DELIVERED,
                ]);

                foreach ($order->items as $item) {
                    DeliveryItem::create([
                        'delivery_id' => $delivery->id,
                        'order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'ordered_quantity' => $item->quantity,
                        'delivered_quantity' => $item->quantity,
                    ]);
                }

                $order->update(['status' => OrderStatus::DELIVERED]);
            });
    }
}
