<?php

namespace Database\Seeders;

use App\Enums\PackagingType;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::query()->where('name', 'Pluimveehandel Noord')->first()
            ?? Supplier::query()->first();

        if (! $supplier) {
            $this->call(SupplierSeeder::class);
            $supplier = Supplier::query()->firstOrFail();
        }

        $this->seedKipfilet($supplier);
        $this->seedHeleKip($supplier);
        $this->seedOtherStandardProducts($supplier);
    }

    protected function seedKipfilet(Supplier $supplier): void
    {
        $kipfilet = Product::query()->firstOrCreate(
            ['name' => 'Kipfilet'],
            [
                'description' => 'Verse kipfilet',
                'product_type' => ProductType::Standard,
                'min_order_quantity' => 1,
                'is_active' => true,
            ],
        );

        ProductPackaging::query()->firstOrCreate(
            ['product_id' => $kipfilet->id, 'packaging_type' => PackagingType::Box, 'weight_kg' => 10],
            ['sort_order' => 0, 'is_default' => true, 'is_active' => true],
        );

        ProductSupplier::query()->firstOrCreate(
            ['product_id' => $kipfilet->id, 'supplier_id' => $supplier->id],
            ['price_per_kg' => 9.80, 'is_default' => true, 'is_active' => true],
        );

        $secondSupplier = Supplier::query()->where('name', 'Kip Express BV')->first();

        if ($secondSupplier !== null) {
            ProductSupplier::query()->firstOrCreate(
                ['product_id' => $kipfilet->id, 'supplier_id' => $secondSupplier->id],
                ['price_per_kg' => 10.50, 'is_default' => false, 'is_active' => true, 'sort_order' => 1],
            );
        }
    }

    protected function seedHeleKip(Supplier $supplier): void
    {
        $heleKip = Product::query()->firstOrCreate(
            ['name' => 'Hele kip'],
            [
                'description' => 'Verse hele kip',
                'product_type' => ProductType::WholeChicken,
                'allows_loading_substitute' => true,
                'min_order_quantity' => 1,
                'is_active' => true,
            ],
        );

        $gramVariants = [
            ['weight_grams' => 750, 'pieces_per_box' => 12, 'box_weight_kg' => 10.5, 'is_default' => true],
            ['weight_grams' => 800, 'pieces_per_box' => 12, 'box_weight_kg' => 10.8, 'is_default' => false],
            ['weight_grams' => 1000, 'pieces_per_box' => 11, 'box_weight_kg' => 11.5, 'is_default' => false],
            ['weight_grams' => 1200, 'pieces_per_box' => 10, 'box_weight_kg' => 12.35, 'is_default' => false],
            ['weight_grams' => 1500, 'pieces_per_box' => 8, 'box_weight_kg' => 12.50, 'is_default' => false],
        ];

        foreach ($gramVariants as $index => $data) {
            ProductGramVariant::query()->firstOrCreate(
                [
                    'product_id' => $heleKip->id,
                    'weight_grams' => $data['weight_grams'],
                ],
                [
                    'pieces_per_box' => $data['pieces_per_box'],
                    'box_weight_kg' => $data['box_weight_kg'],
                    'sort_order' => $index,
                    'is_default' => $data['is_default'],
                    'is_active' => true,
                ],
            );
        }

        ProductSupplier::query()->firstOrCreate(
            ['product_id' => $heleKip->id, 'supplier_id' => $supplier->id],
            ['price_per_kg' => 6.50, 'is_default' => true, 'is_active' => true],
        );
    }

    protected function seedOtherStandardProducts(Supplier $supplier): void
    {
        $otherProducts = [
            ['name' => 'Kippendijen', 'price_per_kg' => 7.20, 'packaging' => PackagingType::Box, 'weight_kg' => 10],
            ['name' => 'Kippenvleugels', 'price_per_kg' => 5.50, 'packaging' => PackagingType::Box, 'weight_kg' => 10],
            ['name' => 'Kippenpoten', 'price_per_kg' => 4.80, 'packaging' => PackagingType::Box, 'weight_kg' => 10],
            ['name' => 'Kipgehakt', 'price_per_kg' => 6.90, 'packaging' => PackagingType::Tray, 'weight_kg' => 1.5],
            ['name' => 'Kipshoarma', 'price_per_kg' => 8.40, 'packaging' => PackagingType::Bag, 'weight_kg' => 2.5],
            ['name' => 'Kipblokjes', 'price_per_kg' => 9.20, 'packaging' => PackagingType::Box, 'weight_kg' => 10],
            ['name' => 'Kippenlever', 'price_per_kg' => 3.60, 'packaging' => PackagingType::Box, 'weight_kg' => 10],
        ];

        foreach ($otherProducts as $index => $data) {
            $product = Product::query()->firstOrCreate(
                ['name' => $data['name']],
                [
                    'description' => "Verse {$data['name']}",
                    'product_type' => ProductType::Standard,
                    'min_order_quantity' => 1,
                    'is_active' => true,
                ],
            );

            ProductPackaging::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'packaging_type' => $data['packaging'],
                    'weight_kg' => $data['weight_kg'],
                ],
                ['sort_order' => 0, 'is_default' => true, 'is_active' => true],
            );

            ProductSupplier::query()->firstOrCreate(
                ['product_id' => $product->id, 'supplier_id' => $supplier->id],
                ['price_per_kg' => $data['price_per_kg'], 'is_default' => true, 'is_active' => true],
            );
        }
    }
}
