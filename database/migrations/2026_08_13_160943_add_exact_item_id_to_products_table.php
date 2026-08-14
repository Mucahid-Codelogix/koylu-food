<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Het Exact-artikel-ID bewaren scheelt bij elke factuurboeking twee
     * API-calls per product (opzoeken + bijwerken).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('exact_item_id')->nullable()->after('exact_article_code');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('exact_item_id');
        });
    }
};
