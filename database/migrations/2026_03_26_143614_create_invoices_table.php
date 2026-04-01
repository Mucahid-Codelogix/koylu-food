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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('invoice_number')->unique();
            $table->string('status')->index();

            $table->decimal('total_amount', 12, 2);

            $table->string('exact_invoice_id')->nullable();

            $table->string('pdf_path')->nullable();
            $table->string('ubl_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
