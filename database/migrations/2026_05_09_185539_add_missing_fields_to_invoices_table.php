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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('invoice_date')->nullable()->after('invoice_number');
            $table->timestamp('sent_at')->nullable()->after('invoice_date');
            $table->timestamp('due_date')->nullable()->after('sent_at');
            $table->text('notes')->nullable()->after('total_amount');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('total_amount');
            $table->decimal('subtotal_amount', 12, 2)->default(0)->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_date', 'sent_at', 'due_date', 'notes', 'vat_amount', 'subtotal_amount']);
        });
    }
};
