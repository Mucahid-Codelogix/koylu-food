<?php

use App\Enums\PackagingType;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type')->default(ProductType::Standard->value)->after('name');
            $table->decimal('min_order_quantity', 10, 2)->default(1)->after('product_type');
        });

        $this->migrateLegacyProductData();

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['unit', 'price', 'min_quantity']);
            $table->dropColumn('exact_article_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit')->default('kg');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('min_quantity', 10, 2)->default(1);
            $table->string('exact_article_code')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (Product::with(['packagings', 'productSuppliers'])->get() as $product) {
            $packaging = $product->packagings->first();
            $productSupplier = $product->productSuppliers->first();

            $product->update([
                'unit' => $packaging?->packaging_type->value ?? 'kg',
                'price' => $productSupplier?->price_per_kg ?? 0,
                'min_quantity' => $product->min_order_quantity,
                'supplier_id' => $productSupplier?->supplier_id,
                'exact_article_code' => $productSupplier?->exact_article_code,
            ]);
        }

        ProductPackaging::query()->delete();
        ProductSupplier::query()->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'min_order_quantity']);
        });
    }

    private function migrateLegacyProductData(): void
    {
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            $productType = str_contains(strtolower($product->name), 'hele kip')
                ? ProductType::WholeChicken->value
                : ProductType::Standard->value;

            DB::table('products')->where('id', $product->id)->update([
                'product_type' => $productType,
                'min_order_quantity' => $product->min_quantity,
            ]);

            $packagingType = $this->mapUnitToPackagingType($product->unit);
            $weightKg = strtolower($product->unit) === 'kg' ? '1.000' : '1.000';

            DB::table('product_packagings')->insert([
                'product_id' => $product->id,
                'packaging_type' => $packagingType->value,
                'weight_kg' => $weightKg,
                'label' => $packagingType === PackagingType::Piece && strtolower($product->unit) === 'kg'
                    ? 'Per kg'
                    : null,
                'min_order_quantity' => $product->min_quantity,
                'sort_order' => 0,
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($product->supplier_id) {
                DB::table('product_suppliers')->insert([
                    'product_id' => $product->id,
                    'supplier_id' => $product->supplier_id,
                    'price_per_kg' => $product->price,
                    'exact_article_code' => $product->exact_article_code,
                    'sort_order' => 0,
                    'is_default' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function mapUnitToPackagingType(string $unit): PackagingType
    {
        return match (strtolower($unit)) {
            'doos' => PackagingType::Box,
            'bakje' => PackagingType::Tray,
            'zak' => PackagingType::Bag,
            'krat' => PackagingType::Crate,
            'stuk' => PackagingType::Piece,
            default => PackagingType::Piece,
        };
    }
};
