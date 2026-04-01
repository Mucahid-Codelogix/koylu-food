<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Order::where('status', 'delivered')->each(function ($order) {

            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-'.strtoupper(Str::random(6)),
                'status' => 'sent',
                'total_amount' => $order->total_price,
            ]);

            $order->update(['status' => 'invoiced']);
        });
    }
}
