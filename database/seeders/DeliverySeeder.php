<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Order::where('status', 'routed')->each(function ($order) {

            $delivery = Delivery::create([
                'order_id' => $order->id,
                'delivered_at' => now(),
                'receiver_name' => 'Test Receiver',
                'status' => 'complete',
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

            $order->update(['status' => 'delivered']);
        });
    }
}
