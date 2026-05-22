<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class CustomerProductPriceSeeder extends Seeder
{
    public function run(): void
    {
        $customer = Customer::query()
            ->where('email', 'inkoop@goudenkip.nl')
            ->first();

        if ($customer === null) {
            return;
        }

        $kipfilet = Product::query()->where('name', 'Kipfilet')->first();

        if ($kipfilet === null) {
            return;
        }

        $offers = [
            ['supplier' => 'Pluimveehandel Noord', 'price' => 8.50],
            ['supplier' => 'Kip Express BV', 'price' => 9.25],
        ];

        foreach ($offers as $offer) {
            $supplier = Supplier::query()->where('name', $offer['supplier'])->first();

            if ($supplier === null) {
                continue;
            }

            $productSupplier = ProductSupplier::query()
                ->where('product_id', $kipfilet->id)
                ->where('supplier_id', $supplier->id)
                ->first();

            if ($productSupplier === null) {
                continue;
            }

            CustomerProductPrice::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_supplier_id' => $productSupplier->id,
                ],
                [
                    'product_id' => $kipfilet->id,
                    'price' => $offer['price'],
                ],
            );
        }
    }
}
