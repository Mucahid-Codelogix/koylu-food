<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 2500);

        return [
            'order_id' => Order::factory()->delivered(),
            'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
            'status' => InvoiceStatus::CONCEPT,
            'invoice_date' => now(),
            'subtotal_amount' => $amount,
            'vat_amount' => round($amount * 0.09, 2),
            'total_amount' => $amount,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::SENT,
            'sent_at' => now(),
            'due_date' => now()->addDays(14),
        ]);
    }
}
