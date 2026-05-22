<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSupplier>
 */
class ProductSupplierFactory extends Factory
{
    protected $model = ProductSupplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'supplier_id' => Supplier::factory(),
            'price_per_kg' => fake()->randomFloat(2, 3, 15),
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
