<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $driver = User::where('role', 'driver')->first();

        $route = Route::create([
            'driver_id' => $driver->id,
            'route_date' => now(),
            'status' => 'planned',
        ]);

        $orders = Order::take(5)->get();

        foreach ($orders as $index => $order) {
            RouteStop::create([
                'route_id' => $route->id,
                'order_id' => $order->id,
                'stop_order' => $index + 1,
            ]);

            $order->update(['status' => 'routed']);
        }
    }
}
