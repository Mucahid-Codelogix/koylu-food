<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('exact_account_id')->nullable()->after('is_active');
            $table->timestamp('exact_synced_at')->nullable()->after('exact_account_id');
            $table->text('exact_sync_error')->nullable()->after('exact_synced_at');

            $table->index('exact_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex(['exact_account_id']);
            $table->dropColumn(['exact_account_id', 'exact_synced_at', 'exact_sync_error']);
        });
    }
};
