<?php

use App\Enums\InvoiceStatus;
use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\AdminDashboardService;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('counts concept invoices and recent failed sync logs', function () {
    Bus::fake([SyncCustomerToExact::class]);

    $conceptBefore = Invoice::query()->where('status', InvoiceStatus::CONCEPT)->count();
    $failedBefore = ExactSyncLog::query()
        ->where('status', ExactSyncLogger::STATUS_FAILED)
        ->where('created_at', '>=', now()->subDay())
        ->count();

    $order = Order::factory()->create();
    Invoice::factory()->create([
        'order_id' => $order->id,
        'status' => InvoiceStatus::CONCEPT,
    ]);

    $customer = Customer::factory()->create();

    ExactSyncLog::query()->create([
        'syncable_type' => Customer::class,
        'syncable_id' => $customer->id,
        'action' => 'push_customer',
        'status' => ExactSyncLogger::STATUS_FAILED,
        'error' => 'Test fout',
    ]);

    $data = app(AdminDashboardService::class)->getData();

    expect($data['concept_invoices_count'])->toBe($conceptBefore + 1)
        ->and($data['exact']['failed_logs_count'])->toBe($failedBefore + 1);
});

it('limits concept invoice previews on the dashboard', function () {
    foreach (range(1, 5) as $index) {
        $order = Order::factory()->create();
        Invoice::factory()->create([
            'order_id' => $order->id,
            'status' => InvoiceStatus::CONCEPT,
            'invoice_number' => 'INV-PREV'.$index,
        ]);
    }

    $data = app(AdminDashboardService::class)->getData();

    expect($data['concept_invoices_count'])->toBe(5)
        ->and($data['concept_invoices_preview'])->toHaveCount(AdminDashboardService::INVOICES_PREVIEW_LIMIT)
        ->and($data['concept_invoices_overflow'])->toBe(2);
});
