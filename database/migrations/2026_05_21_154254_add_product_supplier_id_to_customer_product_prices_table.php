<?php

use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_product_prices', 'product_supplier_id')) {
            Schema::table('customer_product_prices', function (Blueprint $table): void {
                $table->foreignId('product_supplier_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        CustomerProductPrice::query()
            ->whereNull('product_supplier_id')
            ->each(function (CustomerProductPrice $customerPrice): void {
                $productSupplier = Product::query()
                    ->find($customerPrice->product_id)
                    ?->defaultProductSupplier();

                if ($productSupplier === null) {
                    $productSupplier = ProductSupplier::query()
                        ->where('product_id', $customerPrice->product_id)
                        ->where('is_active', true)
                        ->orderByDesc('is_default')
                        ->orderBy('sort_order')
                        ->first();
                }

                if ($productSupplier !== null) {
                    $customerPrice->update(['product_supplier_id' => $productSupplier->id]);
                }
            });

        CustomerProductPrice::query()->whereNull('product_supplier_id')->delete();

        if ($this->hasLegacyUniqueIndex()) {
            Schema::table('customer_product_prices', function (Blueprint $table): void {
                if (! $this->hasCustomerIdIndex()) {
                    $table->index('customer_id', 'customer_product_prices_customer_id_index');
                }

                if (! $this->hasProductIdIndex()) {
                    $table->index('product_id', 'customer_product_prices_product_id_index');
                }

                $table->dropUnique(['customer_id', 'product_id']);
            });
        }

        if (! $this->hasCustomerSupplierUniqueIndex()) {
            Schema::table('customer_product_prices', function (Blueprint $table): void {
                $table->unique(['customer_id', 'product_supplier_id']);
            });
        }

        Schema::table('customer_product_prices', function (Blueprint $table): void {
            $table->decimal('price', 10, 4)->change();
        });
    }

    public function down(): void
    {
        if ($this->hasCustomerSupplierUniqueIndex()) {
            Schema::table('customer_product_prices', function (Blueprint $table): void {
                $table->dropUnique(['customer_id', 'product_supplier_id']);
                $table->unique(['customer_id', 'product_id']);
            });
        }

        if (Schema::hasColumn('customer_product_prices', 'product_supplier_id')) {
            Schema::table('customer_product_prices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_supplier_id');
            });
        }

        Schema::table('customer_product_prices', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->change();
        });
    }

    private function hasLegacyUniqueIndex(): bool
    {
        return collect(Schema::getIndexes('customer_product_prices'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'customer_product_prices_customer_id_product_id_unique');
    }

    private function hasCustomerSupplierUniqueIndex(): bool
    {
        return collect(Schema::getIndexes('customer_product_prices'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'customer_product_prices_customer_id_product_supplier_id_unique');
    }

    private function hasProductIdIndex(): bool
    {
        return collect(Schema::getIndexes('customer_product_prices'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'customer_product_prices_product_id_index');
    }

    private function hasCustomerIdIndex(): bool
    {
        return collect(Schema::getIndexes('customer_product_prices'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'customer_product_prices_customer_id_index');
    }
};
