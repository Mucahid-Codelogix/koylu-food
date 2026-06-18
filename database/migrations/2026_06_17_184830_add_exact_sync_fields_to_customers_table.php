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
            $table->string('exact_account_id')->nullable()->after('is_vat_exempt');
            $table->timestamp('exact_synced_at')->nullable()->after('exact_account_id');
            $table->text('exact_sync_error')->nullable()->after('exact_synced_at');

            $table->index('exact_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['exact_account_id']);
            $table->dropColumn(['exact_account_id', 'exact_synced_at', 'exact_sync_error']);
        });
    }
};
