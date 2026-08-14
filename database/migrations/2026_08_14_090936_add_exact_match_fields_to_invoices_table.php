<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('exact_matched_at')->nullable()->after('exact_cash_entry_at');
            $table->text('exact_match_error')->nullable()->after('exact_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['exact_matched_at', 'exact_match_error']);
        });
    }
};
