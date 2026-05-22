<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_per_kg', 10, 4);
            $table->string('supplier_sku')->nullable();
            $table->string('exact_article_code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
