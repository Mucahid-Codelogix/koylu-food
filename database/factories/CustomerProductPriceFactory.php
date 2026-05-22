<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProductPrice>
 */
class CustomerProductPriceFactory extends Factory
{
    protected $model = CustomerProductPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $productSupplier = ProductSupplier::factory()->create();

        return [
            'customer_id' => Customer::factory(),
            'product_id' => $productSupplier->product_id,
            'product_supplier_id' => $productSupplier->id,
            'price' => fake()->randomFloat(4, 4, 12),
        ];
    }
}
