<?php

use App\Jobs\SyncSupplierToExact;
use App\Models\Supplier;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSupplierSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('stores the exact account id after a successful supplier sync', function () {
    $supplier = Supplier::factory()->create([
        'name' => 'Sync Target Leverancier',
    ]);

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactSupplierSyncService::class, function ($mock) use ($supplier): void {
        $mock->shouldReceive('sync')
            ->once()
            ->withArgs(fn (Supplier $subject): bool => $subject->is($supplier))
            ->andReturn('11111111-2222-3333-4444-555555555555');
    });

    (new SyncSupplierToExact($supplier))->handle(
        app(ExactSupplierSyncService::class),
        app(ExactOnlineClient::class),
    );

    $supplier->refresh();

    expect($supplier->exact_account_id)->toBe('11111111-2222-3333-4444-555555555555')
        ->and($supplier->exact_synced_at)->not->toBeNull()
        ->and($supplier->exact_sync_error)->toBeNull();
});

it('records an error when exact is not connected for supplier sync', function () {
    $supplier = Supplier::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(false);
    });

    $this->mock(ExactSupplierSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('sync');
    });

    (new SyncSupplierToExact($supplier))->handle(
        app(ExactSupplierSyncService::class),
        app(ExactOnlineClient::class),
    );

    $supplier->refresh();

    expect($supplier->exact_sync_error)->toBe('Exact Online is niet gekoppeld.')
        ->and($supplier->exact_account_id)->toBeNull();
});

it('records sync failures on the supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactSupplierSyncService::class, function ($mock): void {
        $mock->shouldReceive('sync')
            ->once()
            ->andThrow(new ExactApiException('Exact API fout'));
    });

    expect(fn () => (new SyncSupplierToExact($supplier))->handle(
        app(ExactSupplierSyncService::class),
        app(ExactOnlineClient::class),
    ))->toThrow(ExactApiException::class);

    $supplier->refresh();

    expect($supplier->exact_sync_error)->toBe('Exact API fout');
});

it('dispatches a sync job when a supplier is created', function () {
    Queue::fake();

    Supplier::factory()->create();

    Queue::assertPushed(SyncSupplierToExact::class);
});

it('dispatches a sync job when supplier data changes', function () {
    Queue::fake();

    $supplier = Supplier::factory()->create();

    Queue::assertPushed(SyncSupplierToExact::class, 1);

    $supplier->update(['name' => 'Nieuwe leveranciersnaam']);

    Queue::assertPushed(SyncSupplierToExact::class, 2);
});

it('does not dispatch a sync job when only exact sync metadata changes', function () {
    Queue::fake();

    $supplier = Supplier::factory()->create();

    Queue::assertPushed(SyncSupplierToExact::class, 1);

    $supplier->update([
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_synced_at' => now(),
        'exact_sync_error' => null,
    ]);

    Queue::assertPushed(SyncSupplierToExact::class, 1);
});

it('does not dispatch a sync job when a supplier is imported with an exact account id', function () {
    Queue::fake();

    Supplier::query()->create([
        'name' => 'Imported Supplier',
        'exact_account_id' => '11111111-2222-3333-4444-555555555555',
        'exact_synced_at' => now(),
    ]);

    Queue::assertNotPushed(SyncSupplierToExact::class);
});
