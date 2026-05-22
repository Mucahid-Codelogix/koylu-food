<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductGramVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductGramVariant>
 */
class ProductGramVariantFactory extends Factory
{
    protected $model = ProductGramVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $weightGrams = fake()->randomElement([700, 750, 800, 900, 1000, 1200]);
        $piecesPerBox = fake()->numberBetween(8, 14);

        return [
            'product_id' => Product::factory(),
            'weight_grams' => $weightGrams,
            'pieces_per_box' => $piecesPerBox,
            'box_weight_kg' => round($weightGrams * $piecesPerBox / 1000, 3),
            'sort_order' => 0,
            'is_default' => true,
            'is_active' => true,
        ];
    }
}
