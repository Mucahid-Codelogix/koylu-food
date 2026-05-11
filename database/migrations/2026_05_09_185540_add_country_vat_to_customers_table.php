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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('vat_number')->nullable()->after('country');
            $table->string('exact_article_suffix')->nullable()->after('vat_number'); // '05' of '005'
            $table->boolean('is_vat_exempt')->default(false)->after('exact_article_suffix'); // BE klanten
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['vat_number', 'exact_article_suffix', 'is_vat_exempt']);
        });
    }
};
