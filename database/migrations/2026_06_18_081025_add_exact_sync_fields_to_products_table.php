<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('exact_article_code')->nullable()->after('vat_category');
            $table->timestamp('exact_synced_at')->nullable()->after('exact_article_code');
            $table->text('exact_sync_error')->nullable()->after('exact_synced_at');

            $table->index('exact_article_code');
        });

        foreach (DB::table('products')->pluck('id') as $productId) {
            $code = DB::table('product_suppliers')
                ->where('product_id', $productId)
                ->whereNotNull('exact_article_code')
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->value('exact_article_code');

            if ($code !== null) {
                DB::table('products')
                    ->where('id', $productId)
                    ->update(['exact_article_code' => $code]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['exact_article_code']);
            $table->dropColumn(['exact_article_code', 'exact_synced_at', 'exact_sync_error']);
        });
    }
};
