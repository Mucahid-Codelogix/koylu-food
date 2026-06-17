<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('vat_category')->default('high')->after('is_active');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('vat_rate', 5, 2)->nullable()->after('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('vat_rate');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('vat_category');
        });
    }
};
