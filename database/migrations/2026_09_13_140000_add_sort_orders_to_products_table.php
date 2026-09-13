<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Twee volgordes die de admin zelf bepaalt: de shopvolgorde (wat klanten zien)
     * en de laadvolgorde (hoe chauffeurs laden). Bestaande producten krijgen in
     * beide hun huidige, alfabetische volgorde.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('shop_sort_order')->default(0)->after('name')->index();
            $table->unsignedInteger('loading_sort_order')->default(0)->after('shop_sort_order')->index();
        });

        DB::table('products')
            ->orderBy('name')
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id, int $index) => DB::table('products')
                ->where('id', $id)
                ->update([
                    'shop_sort_order' => $index + 1,
                    'loading_sort_order' => $index + 1,
                ]));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['shop_sort_order']);
            $table->dropIndex(['loading_sort_order']);
            $table->dropColumn(['shop_sort_order', 'loading_sort_order']);
        });
    }
};
