<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $deliveredOrders = Order::query()
            ->where('status', OrderStatus::DELIVERED)
            ->whereDoesntHave('invoice')
            ->get();

        foreach ($deliveredOrders as $index => $order) {
            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => 'INV-'.strtoupper(Str::random(6)),
                'status' => $index === 0 ? InvoiceStatus::CONCEPT : InvoiceStatus::SENT,
                'invoice_date' => now()->subDay(),
                'sent_at' => $index === 0 ? null : now()->subDay(),
                'due_date' => now()->addDays(14),
                'subtotal_amount' => $order->total_price,
                'vat_amount' => round((float) $order->total_price * 0.09, 2),
                'total_amount' => $order->total_price,
            ]);
        }
    }
}
