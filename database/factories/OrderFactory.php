<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.strtoupper(Str::random(8)),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::PLACED,
            'order_date' => now(),
            'delivery_date' => now()->addDay(),
            'total_price' => 0,
            'notes' => null,
        ];
    }

    public function placed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::PLACED,
        ]);
    }

    public function routed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::ROUTED,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::DELIVERED,
        ]);
    }
}
