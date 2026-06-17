<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(InvoiceService::class);

        Order::query()
            ->where('status', OrderStatus::DELIVERED)
            ->whereDoesntHave('invoice')
            ->with(['delivery.items', 'items', 'customer'])
            ->get()
            ->each(function (Order $order) use ($service): void {
                if (! $order->delivery) {
                    return;
                }

                $service->createFromDelivery($order->delivery);
            });
    }
}
