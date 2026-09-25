<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gram_variant_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_gram_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_gram_variant_id', 'product_supplier_id'], 'gram_variant_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_gram_variant_supplier');
    }
};
