<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::all();

        Customer::all()->each(function ($customer) use ($products) {

            for ($i = 0; $i < 3; $i++) {

                $order = Order::create([
                    'order_number' => 'ORD-'.strtoupper(Str::random(6)),
                    'customer_id' => $customer->id,
                    'status' => 'placed',
                    'order_date' => now(),
                    'delivery_date' => now()->addDays(1),
                ]);

                $total = 0;

                foreach ($products->random(2) as $product) {

                    $qty = rand(1, 10);
                    $subtotal = $qty * $product->price;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit' => $product->unit,
                        'quantity' => $qty,
                        'unit_price' => $product->price,
                        'subtotal' => $subtotal,
                    ]);

                    $total += $subtotal;
                }

                $order->update([
                    'total_price' => $total,
                ]);
            }
        });
    }
}
