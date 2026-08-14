<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('payment_method')->default('bank')->after('status')->index();
            $table->timestamp('paid_at')->nullable()->after('sent_at');
            $table->decimal('paid_amount', 12, 2)->nullable()->after('total_amount');

            $table->string('exact_cash_entry_id')->nullable()->after('exact_synced_at');
            $table->timestamp('exact_cash_entry_at')->nullable()->after('exact_cash_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['payment_method']);
            $table->dropColumn([
                'payment_method',
                'paid_at',
                'paid_amount',
                'exact_cash_entry_id',
                'exact_cash_entry_at',
            ]);
        });
    }
};
