<?php

namespace Database\Factories;

use App\Enums\RouteStopStatus;
use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'order_id' => Order::factory()->placed(),
            'stop_order' => 1,
            'status' => RouteStopStatus::PENDING,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => RouteStopStatus::DELIVERED,
        ]);
    }
}
