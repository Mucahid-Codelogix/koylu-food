<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\Product;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $builder = app(DemoOrderBuilder::class);

        $standardProducts = Product::query()
            ->where('product_type', ProductType::Standard)
            ->with(['activePackagings', 'activeProductSuppliers.supplier'])
            ->get()
            ->filter(fn (Product $product) => $product->defaultPackaging() && $product->defaultProductSupplier());

        $wholeChicken = Product::query()
            ->where('product_type', ProductType::WholeChicken)
            ->with(['activeGramVariants', 'activeProductSuppliers.supplier'])
            ->first();

        $customers = Customer::all();

        foreach ($customers as $index => $customer) {
            $this->seedUnroutedOrders($builder, $customer, $standardProducts, $wholeChicken, $index);
        }
    }

    /**
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedUnroutedOrders(
        DemoOrderBuilder $builder,
        Customer $customer,
        $standardProducts,
        ?Product $wholeChicken,
        int $customerIndex,
    ): void {
        $order = $builder->createPlacedOrder(
            $customer,
            DemoOrderBuilder::uniqueOrderNumber('DEMO-NEW'),
            now()->addDays(2),
            'Nog niet ingepland — zichtbaar op admin dashboard',
        );

        $builder->addStandardLine(
            $order,
            $standardProducts->random(),
            $customer,
            (float) rand(1, 3),
        );

        if ($wholeChicken) {
            $variant = $wholeChicken->activeGramVariants->random();
            $builder->addWholeChickenLine($order, $wholeChicken, $customer, (float) rand(1, 2), $variant);
        }

        $builder->recalculateTotal($order);

        $order = $builder->createPlacedOrder(
            $customer,
            DemoOrderBuilder::uniqueOrderNumber('DEMO-NEW'),
            now()->addDays(3),
        );

        $builder->addStandardLine(
            $order,
            $standardProducts->skip($customerIndex % max(1, $standardProducts->count()))->first() ?? $standardProducts->first(),
            $customer,
            (float) rand(2, 4),
        );

        $builder->recalculateTotal($order);
    }
}
