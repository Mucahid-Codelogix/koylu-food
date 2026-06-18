<?php

use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCustomerSyncService;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('stores the exact account id after a successful sync', function () {
    $customer = Customer::factory()->create([
        'company_name' => 'Sync Target B.V.',
    ]);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactCustomerSyncService::class, function ($mock) use ($customer): void {
        $mock->shouldReceive('sync')
            ->once()
            ->withArgs(fn (Customer $subject): bool => $subject->is($customer))
            ->andReturn('11111111-2222-3333-4444-555555555555');
    });

    (new SyncCustomerToExact($customer))->handle(
        app(ExactCustomerSyncService::class),
        app(ExactOnlineClient::class),
    );

    $customer->refresh();

    expect($customer->exact_account_id)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($customer->exact_synced_at)->not->toBeNull()
        ->and($customer->exact_sync_error)->toBeNull();
});

it('records an error when exact is not connected', function () {
    $customer = Customer::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(false);
    });

    $this->mock(ExactCustomerSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('sync');
    });

    (new SyncCustomerToExact($customer))->handle(
        app(ExactCustomerSyncService::class),
        app(ExactOnlineClient::class),
    );

    $customer->refresh();

    expect($customer->exact_sync_error)->toBe('Exact Online is niet gekoppeld.')
        ->and($customer->exact_account_id)->toBeNull();
});

it('records sync failures on the customer', function () {
    $customer = Customer::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactCustomerSyncService::class, function ($mock): void {
        $mock->shouldReceive('sync')
            ->once()
            ->andThrow(new ExactApiException('Exact API fout'));
    });

    expect(fn () => (new SyncCustomerToExact($customer))->handle(
        app(ExactCustomerSyncService::class),
        app(ExactOnlineClient::class),
    ))->toThrow(ExactApiException::class);

    $customer->refresh();

    expect($customer->exact_sync_error)->toBe('Exact API fout');
});

it('dispatches a sync job when a customer is created', function () {
    Queue::fake();

    Customer::factory()->create();

    Queue::assertPushed(SyncCustomerToExact::class);
});

it('dispatches a sync job when customer data changes', function () {
    Queue::fake();

    $customer = Customer::factory()->create();

    Queue::assertPushed(SyncCustomerToExact::class, 1);

    $customer->update(['company_name' => 'Nieuwe naam B.V.']);

    Queue::assertPushed(SyncCustomerToExact::class, 2);
});

it('does not dispatch a sync job when only exact sync metadata changes', function () {
    Queue::fake();

    $customer = Customer::factory()->create();

    Queue::assertPushed(SyncCustomerToExact::class, 1);

    $customer->update([
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_synced_at' => now(),
        'exact_sync_error' => null,
    ]);

    Queue::assertPushed(SyncCustomerToExact::class, 1);
});

it('does not dispatch a sync job when a customer is imported with an exact account id', function () {
    Queue::fake();

    Customer::query()->create([
        'company_name' => 'Imported',
        'address' => 'Straat 1',
        'postal_code' => '1234AB',
        'city' => 'Groningen',
        'country' => 'NL',
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_synced_at' => now(),
    ]);

    Queue::assertNotPushed(SyncCustomerToExact::class);
});
