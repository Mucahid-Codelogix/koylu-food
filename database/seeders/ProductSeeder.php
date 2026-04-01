<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::insert([
            [
                'name' => 'Hele kip',
                'description' => 'Verse hele kip',
                'unit' => 'stuk',
                'price' => 6.50,
                'min_quantity' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kipfilet',
                'description' => 'Verse kipfilet',
                'unit' => 'kg',
                'price' => 9.80,
                'min_quantity' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kippendijen',
                'description' => 'Kippendijen zonder bot',
                'unit' => 'kg',
                'price' => 7.20,
                'min_quantity' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Kippenvleugels',
                'description' => 'Verse kippenvleugels',
                'unit' => 'kg',
                'price' => 5.50,
                'min_quantity' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Kippenpoten',
                'description' => 'Hele kippenpoten',
                'unit' => 'kg',
                'price' => 4.80,
                'min_quantity' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Kipgehakt',
                'description' => 'Vers kipgehakt',
                'unit' => 'kg',
                'price' => 6.90,
                'min_quantity' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kipschnitzel',
                'description' => 'Gepaneerde kipschnitzel',
                'unit' => 'stuk',
                'price' => 2.20,
                'min_quantity' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Kipshoarma',
                'description' => 'Gekruide kipshoarma',
                'unit' => 'kg',
                'price' => 8.40,
                'min_quantity' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kipblokjes',
                'description' => 'Gesneden kipblokjes',
                'unit' => 'kg',
                'price' => 9.20,
                'min_quantity' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Kippenlever',
                'description' => 'Verse kippenlever',
                'unit' => 'kg',
                'price' => 3.60,
                'min_quantity' => 2,
                'is_active' => true,
            ],
        ]);
    }
}
