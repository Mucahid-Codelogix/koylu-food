<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('allows_loading_substitute')
                ->default(false)
                ->after('product_type');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_type')->nullable()->after('product_id');

            $table->foreignId('product_packaging_id')->nullable()->after('product_type')->constrained()->nullOnDelete();
            $table->foreignId('product_supplier_id')->nullable()->after('product_packaging_id')->constrained()->nullOnDelete();
            $table->foreignId('product_gram_variant_id')->nullable()->after('product_supplier_id')->constrained()->nullOnDelete();

            $table->foreignId('supplier_id')->nullable()->after('product_gram_variant_id')->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable()->after('supplier_id');
            $table->string('packaging_label')->nullable()->after('unit');

            $table->unsignedInteger('weight_grams')->nullable()->after('packaging_label');
            $table->unsignedInteger('pieces_per_box')->nullable()->after('weight_grams');
            $table->decimal('box_weight_kg', 10, 3)->nullable()->after('pieces_per_box');
            $table->decimal('price_per_kg', 10, 4)->nullable()->after('box_weight_kg');
            $table->decimal('ordered_pieces', 10, 2)->nullable()->after('quantity');
            $table->decimal('ordered_total_weight_kg', 10, 3)->nullable()->after('ordered_pieces');

            $table->foreignId('loaded_gram_variant_id')->nullable()->after('ordered_total_weight_kg')->constrained('product_gram_variants')->nullOnDelete();
            $table->foreignId('substituted_from_gram_variant_id')->nullable()->after('loaded_gram_variant_id')->constrained('product_gram_variants')->nullOnDelete();
            $table->unsignedInteger('loaded_weight_grams')->nullable()->after('substituted_from_gram_variant_id');
            $table->unsignedInteger('loaded_pieces_per_box')->nullable()->after('loaded_weight_grams');
            $table->decimal('loaded_box_weight_kg', 10, 3)->nullable()->after('loaded_pieces_per_box');
            $table->decimal('loaded_packaging_quantity', 10, 2)->nullable()->after('loaded_box_weight_kg');
            $table->decimal('loaded_total_weight_kg', 10, 3)->nullable()->after('loaded_packaging_quantity');
            $table->decimal('loaded_pieces', 10, 2)->nullable()->after('loaded_total_weight_kg');
            $table->text('loading_substitution_reason')->nullable()->after('loaded_pieces');
            $table->timestamp('loaded_at')->nullable()->after('loading_substitution_reason');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loaded_gram_variant_id');
            $table->dropConstrainedForeignId('substituted_from_gram_variant_id');
            $table->dropConstrainedForeignId('product_gram_variant_id');
            $table->dropConstrainedForeignId('product_packaging_id');
            $table->dropConstrainedForeignId('product_supplier_id');
            $table->dropConstrainedForeignId('supplier_id');

            $table->dropColumn([
                'product_type',
                'supplier_name',
                'packaging_label',
                'weight_grams',
                'pieces_per_box',
                'box_weight_kg',
                'price_per_kg',
                'ordered_pieces',
                'ordered_total_weight_kg',
                'loaded_weight_grams',
                'loaded_pieces_per_box',
                'loaded_box_weight_kg',
                'loaded_packaging_quantity',
                'loaded_total_weight_kg',
                'loaded_pieces',
                'loading_substitution_reason',
                'loaded_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('allows_loading_substitute');
        });
    }
};
