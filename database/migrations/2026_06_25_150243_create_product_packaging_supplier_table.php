<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_packaging_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_packaging_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_packaging_id', 'product_supplier_id'], 'pkg_supplier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packaging_supplier');
    }
};
