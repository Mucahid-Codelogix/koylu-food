<?php

use App\Jobs\SyncCustomerToExact;
use App\Jobs\SyncProductToExact;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Models\Product;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use App\Services\Exact\ExactSyncRetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

it('can retry a failed customer sync log when exact is connected', function () {
    Bus::fake([SyncCustomerToExact::class]);

    $customer = Customer::factory()->create(['exact_sync_error' => 'API timeout']);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->andReturn(true);
    });

    $log = ExactSyncLog::query()->create([
        'syncable_type' => Customer::class,
        'syncable_id' => $customer->id,
        'action' => 'push_customer',
        'status' => ExactSyncLogger::STATUS_FAILED,
        'error' => 'API timeout',
    ]);

    $service = app(ExactSyncRetryService::class);

    expect($service->canRetry($log))->toBeTrue();

    $service->retry($log);

    Bus::assertDispatched(SyncCustomerToExact::class, fn (SyncCustomerToExact $job): bool => $job->customer->is($customer));

    expect($customer->fresh()->exact_sync_error)->toBeNull();
});

it('cannot retry when exact is not connected', function () {
    Bus::fake([SyncProductToExact::class]);

    $product = Product::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->andReturn(false);
    });

    $log = ExactSyncLog::query()->create([
        'syncable_type' => Product::class,
        'syncable_id' => $product->id,
        'action' => 'push_product',
        'status' => ExactSyncLogger::STATUS_FAILED,
        'error' => 'Timeout',
    ]);

    expect(app(ExactSyncRetryService::class)->canRetry($log))->toBeFalse();
});

it('cannot retry successful sync logs', function () {
    $customer = Customer::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->andReturn(true);
    });

    $log = ExactSyncLog::query()->create([
        'syncable_type' => Customer::class,
        'syncable_id' => $customer->id,
        'action' => 'push_customer',
        'status' => ExactSyncLogger::STATUS_SUCCESS,
    ]);

    expect(app(ExactSyncRetryService::class)->canRetry($log))->toBeFalse();
});
