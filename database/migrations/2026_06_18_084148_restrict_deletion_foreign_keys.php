<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });

        Schema::table('crate_transactions', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
        });

        Schema::table('customer_product_prices', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['product_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });

        Schema::table('route_stops', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('route_stops', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });

        Schema::table('customer_product_prices', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['product_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('crate_transactions', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }
};
