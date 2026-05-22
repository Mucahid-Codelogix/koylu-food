<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->delivered(),
            'delivered_at' => now(),
            'receiver_name' => fake()->name(),
            'status' => DeliveryStatus::DELIVERED,
        ];
    }
}
