<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `signature_path` bevat de handtekening van de ontvanger; de bezorger
     * tekent voortaan apart voor de aflevering.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->string('driver_signature_path')->nullable()->after('signature_path');
            $table->string('driver_name')->nullable()->after('driver_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn(['driver_signature_path', 'driver_name']);
        });
    }
};
