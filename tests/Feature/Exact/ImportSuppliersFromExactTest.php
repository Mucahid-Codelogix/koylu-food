<?php

use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Jobs\ImportSuppliersFromExact;
use App\Jobs\SyncSupplierToExact;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCustomerImportResult;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSupplierImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('imports suppliers from exact via the job', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactSupplierImportService::class, function ($mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andReturn(new ExactCustomerImportResult(created: 3, updated: 1, skipped: 0));
    });

    (new ImportSuppliersFromExact($admin->id))->handle(
        app(ExactSupplierImportService::class),
        app(ExactOnlineClient::class),
    );

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

it('notifies the user when supplier import fails', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactSupplierImportService::class, function ($mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andThrow(new ExactApiException('Exact API fout'));
    });

    expect(fn () => (new ImportSuppliersFromExact($admin->id))->handle(
        app(ExactSupplierImportService::class),
        app(ExactOnlineClient::class),
    ))->toThrow(ExactApiException::class);

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

it('imports new suppliers without dispatching a push back to exact', function () {
    Queue::fake();

    Supplier::withoutEvents(function (): void {
        Supplier::query()->create([
            'name' => 'Imported Crediteur',
            'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'exact_synced_at' => now(),
        ]);
    });

    Queue::assertNotPushed(SyncSupplierToExact::class);
});

it('does not dispatch sync when a supplier is created with an exact account id', function () {
    Queue::fake();

    Supplier::query()->create([
        'name' => 'Pre-linked Supplier',
        'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'exact_synced_at' => now(),
    ]);

    Queue::assertNotPushed(SyncSupplierToExact::class);
});

it('dispatches the import job from the suppliers list action', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->actingAs($admin);

    Livewire::test(ListSuppliers::class)
        ->callAction('importFromExact')
        ->assertNotified();

    Queue::assertPushed(ImportSuppliersFromExact::class, fn (ImportSuppliersFromExact $job): bool => $job->initiatedByUserId === $admin->id);
});
