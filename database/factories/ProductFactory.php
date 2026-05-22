<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'product_type' => ProductType::Standard,
            'allows_loading_substitute' => false,
            'min_order_quantity' => 1,
            'is_active' => true,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => [
            'product_type' => ProductType::Standard,
            'allows_loading_substitute' => false,
        ])->afterCreating(function (Product $product): void {
            if ($product->packagings()->doesntExist()) {
                ProductPackaging::factory()->for($product)->create();
            }

            if ($product->productSuppliers()->doesntExist()) {
                ProductSupplier::factory()->for($product)->create();
            }
        });
    }

    public function wholeChicken(): static
    {
        return $this->state(fn () => [
            'product_type' => ProductType::WholeChicken,
            'allows_loading_substitute' => true,
        ])->afterCreating(function (Product $product): void {
            if ($product->gramVariants()->doesntExist()) {
                ProductGramVariant::factory()->for($product)->create();
            }

            if ($product->productSuppliers()->doesntExist()) {
                ProductSupplier::factory()->for($product)->create();
            }
        });
    }
}
