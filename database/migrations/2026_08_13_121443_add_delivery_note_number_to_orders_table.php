<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('delivery_note_number')->nullable()->unique()->after('order_number');
        });

        $this->backfillExistingRoutedOrders();
    }

    /**
     * Orders die al ingepland of geleverd zijn hebben met terugwerkende kracht
     * ook een leverbonnummer nodig, anders kan hun leverbon niet gedrukt worden.
     */
    private function backfillExistingRoutedOrders(): void
    {
        $orders = DB::table('orders')
            ->whereIn('status', ['routed', 'delivered'])
            ->whereNull('delivery_note_number')
            ->orderBy('id')
            ->get(['id', 'created_at']);

        $sequenceByYear = [];

        foreach ($orders as $order) {
            $year = $order->created_at ? Carbon::parse($order->created_at)->year : now()->year;
            $sequenceByYear[$year] = ($sequenceByYear[$year] ?? 0) + 1;

            DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'delivery_note_number' => sprintf('LB%d-%04d', $year, $sequenceByYear[$year]),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['delivery_note_number']);
            $table->dropColumn('delivery_note_number');
        });
    }
};
