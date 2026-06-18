<?php

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Jobs\ImportCustomersFromExact;
use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;
use App\Models\User;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCustomerImportResult;
use App\Services\Exact\ExactCustomerImportService;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('imports customers from exact via the job', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactCustomerImportService::class, function ($mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andReturn(new ExactCustomerImportResult(created: 5, updated: 2, skipped: 1));
    });

    (new ImportCustomersFromExact($admin->id))->handle(
        app(ExactCustomerImportService::class),
        app(ExactOnlineClient::class),
    );

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

it('notifies the user when import fails', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactCustomerImportService::class, function ($mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andThrow(new ExactApiException('Exact API fout'));
    });

    expect(fn () => (new ImportCustomersFromExact($admin->id))->handle(
        app(ExactCustomerImportService::class),
        app(ExactOnlineClient::class),
    ))->toThrow(ExactApiException::class);

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

it('imports new customers without dispatching a push back to exact', function () {
    Queue::fake();

    Customer::withoutEvents(function (): void {
        Customer::query()->create([
            'company_name' => 'Imported Debiteur',
            'address' => 'Straat 1',
            'postal_code' => '1234AB',
            'city' => 'Groningen',
            'country' => 'NL',
            'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'exact_synced_at' => now(),
        ]);
    });

    Queue::assertNotPushed(SyncCustomerToExact::class);
});

it('does not dispatch sync when a customer is created with an exact account id', function () {
    Queue::fake();

    Customer::query()->create([
        'company_name' => 'Pre-linked',
        'address' => 'Straat 1',
        'postal_code' => '1234AB',
        'city' => 'Groningen',
        'country' => 'NL',
        'exact_account_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'exact_synced_at' => now(),
    ]);

    Queue::assertNotPushed(SyncCustomerToExact::class);
});

it('dispatches the import job from the customers list action', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->actingAs($admin);

    Livewire::test(ListCustomers::class)
        ->callAction('importFromExact')
        ->assertNotified();

    Queue::assertPushed(ImportCustomersFromExact::class, fn (ImportCustomersFromExact $job): bool => $job->initiatedByUserId === $admin->id);
});
