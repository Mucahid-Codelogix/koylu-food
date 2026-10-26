<?php

use App\Filament\Customer\Resources\Orders\Pages\ListOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toont het ordertotaal in het klantportaal in euro', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->customer()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->placed()->create([
        'customer_id' => $customer->id,
        'total_price' => 49.90,
    ]);

    Filament::setCurrentPanel('customer');

    Livewire::actingAs($user)
        ->test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->assertTableColumnFormattedStateSet('total_price', "€\u{A0}49,90", $order)
        ->assertDontSee('$49');
});
