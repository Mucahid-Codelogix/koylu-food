<?php

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toont de nieuwste facturen bovenaan', function () {
    $this->actingAs(User::factory()->admin()->create());

    $oudste = Invoice::factory()->create([
        'order_id' => Order::factory()->for(Customer::factory())->create()->id,
        'created_at' => now()->subWeek(),
    ]);

    $nieuwste = Invoice::factory()->create([
        'order_id' => Order::factory()->for(Customer::factory())->create()->id,
        'created_at' => now(),
    ]);

    Livewire::test(ListInvoices::class)
        ->assertCanSeeTableRecords([$nieuwste, $oudste], inOrder: true);
});

it('toont de nieuwste bestellingen bovenaan', function () {
    $this->actingAs(User::factory()->admin()->create());

    $oudste = Order::factory()->for(Customer::factory())->create(['created_at' => now()->subWeek()]);
    $nieuwste = Order::factory()->for(Customer::factory())->create(['created_at' => now()]);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$nieuwste, $oudste], inOrder: true);
});
