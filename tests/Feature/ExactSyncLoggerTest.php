<?php

use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Models\Product;
use App\Services\AdminDashboardService;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('records successful and failed exact sync logs', function () {
    Bus::fake([SyncCustomerToExact::class]);

    $customer = Customer::factory()->create();

    ExactSyncLogger::success($customer, 'push_customer', 'Debiteur gesynchroniseerd.');
    ExactSyncLogger::failed($customer, 'push_customer', 'API timeout');

    expect(ExactSyncLog::query()->where('syncable_id', $customer->id)->count())->toBe(2)
        ->and(ExactSyncLog::query()->where('status', ExactSyncLogger::STATUS_SUCCESS)->count())->toBe(1)
        ->and(ExactSyncLog::query()->where('status', ExactSyncLogger::STATUS_FAILED)->count())->toBe(1);
});

it('includes exact connection status on the admin dashboard', function () {
    Bus::fake([SyncCustomerToExact::class]);

    Customer::factory()->create(['exact_sync_error' => 'Sync mislukt']);
    Product::factory()->create(['exact_sync_error' => 'Artikel fout']);

    $data = app(AdminDashboardService::class)->getData();

    expect($data['exact']['is_connected'])->toBeFalse()
        ->and($data['exact']['sync_errors_count'])->toBe(2);
});
