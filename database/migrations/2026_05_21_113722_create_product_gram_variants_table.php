<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_gram_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('weight_grams');
            $table->unsignedInteger('pieces_per_box');
            $table->decimal('box_weight_kg', 10, 3);
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'weight_grams']);
            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_gram_variants');
    }
};
