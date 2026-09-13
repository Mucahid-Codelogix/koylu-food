<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bij contante betaling tekent de bezorger apart voor ontvangst van het geld.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->string('cash_signature_path')->nullable()->after('driver_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn('cash_signature_path');
        });
    }
};
