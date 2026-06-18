<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('exact_document_number')->nullable()->after('exact_invoice_id');
            $table->timestamp('exact_synced_at')->nullable()->after('exact_document_number');
            $table->text('exact_sync_error')->nullable()->after('exact_synced_at');

            $table->index('exact_document_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['exact_document_number']);
            $table->dropColumn(['exact_document_number', 'exact_synced_at', 'exact_sync_error']);
        });
    }
};
