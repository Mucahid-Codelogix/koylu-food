<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = User::where('role', 'driver')->get();
        $orders = Order::all();

        // Verdeel orders in chunks van 5 voor elke route
        $orderChunks = $orders->chunk(5);

        foreach ($orderChunks as $chunk) {
            $driver = $drivers->random();
            $vehicle = Vehicle::inRandomOrder()->first();

            $route = Route::create([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'route_date' => now()->addDays(rand(0, 7)),
                'status' => 'planned',
            ]);

            foreach ($chunk as $index => $order) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'order_id' => $order->id,
                    'stop_order' => $index + 1,
                ]);

                $order->update(['status' => 'routed']);
            }
        }
    }
}
