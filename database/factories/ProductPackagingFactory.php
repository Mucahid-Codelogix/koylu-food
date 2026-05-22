<?php

namespace Database\Factories;

use App\Enums\PackagingType;
use App\Models\Product;
use App\Models\ProductPackaging;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPackaging>
 */
class ProductPackagingFactory extends Factory
{
    protected $model = ProductPackaging::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'packaging_type' => PackagingType::Box,
            'weight_kg' => fake()->randomElement([1.5, 2.5, 10]),
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
