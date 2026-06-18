<?php

use App\Enums\RouteStopStatus;
use App\Filament\Driver\Pages\DriverDeliveryPhase;
use App\Models\Customer;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Support\DeliveryDeviationSummary;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores return notes on partial delivery items', function () {
    Storage::fake(UploadStorage::diskName());

    $driver = User::factory()->driver()->create();
    $route = Route::factory()->loadingCompleted()->create([
        'driver_id' => $driver->id,
    ]);
    $order = Order::factory()->placed()->for(Customer::factory())->create();
    $orderItem = OrderItem::factory()->for($order)->create([
        'quantity' => 5,
        'product_name' => 'Kipfilet',
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => Order::factory()->placed()->for(Customer::factory())->create()->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);

    Livewire::actingAs($driver)
        ->withQueryParams(['routeId' => $route->id])
        ->test(DriverDeliveryPhase::class)
        ->set('deliveryData.'.$orderItem->id.'.delivered_quantity', 3)
        ->set('deliveryData.'.$orderItem->id.'.return_note', '2 colli retour, beschadigd')
        ->set('receiverName', 'Jan Jansen')
        ->set('signature', 'data:image/png;base64,iVBORw0KGgo=')
        ->call('saveDelivery')
        ->assertNotified();

    $deliveryItem = DeliveryItem::query()
        ->where('order_item_id', $orderItem->id)
        ->first();

    expect($deliveryItem)->not->toBeNull()
        ->and($deliveryItem->delivered_quantity)->toBe(3)
        ->and($deliveryItem->return_note)->toBe('2 colli retour, beschadigd');
});

it('formats delivery deviations for admin views', function () {
    ['delivery' => $delivery] = makeInvoiceOrder([
        [
            'quantity' => 5,
            'box_weight_kg' => 3,
            'price_per_kg' => 7,
            'vat_rate' => 21,
            'delivered' => 3,
            'return_note' => '2 colli retour',
        ],
    ]);

    $item = $delivery->items->first();
    $item->update(['return_note' => '2 colli retour']);

    expect(DeliveryDeviationSummary::html($delivery->fresh('items.orderItem')))
        ->toContain($item->orderItem->product_name)
        ->toContain('Geleverd: 3/5')
        ->toContain('Retour: 2 colli retour');
});

it('does not store return notes when delivery is complete', function () {
    Storage::fake(UploadStorage::diskName());

    $driver = User::factory()->driver()->create();
    $route = Route::factory()->loadingCompleted()->create([
        'driver_id' => $driver->id,
    ]);
    $order = Order::factory()->placed()->for(Customer::factory())->create();
    $orderItem = OrderItem::factory()->for($order)->create(['quantity' => 5]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => Order::factory()->placed()->for(Customer::factory())->create()->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);

    Livewire::actingAs($driver)
        ->withQueryParams(['routeId' => $route->id])
        ->test(DriverDeliveryPhase::class)
        ->set('deliveryData.'.$orderItem->id.'.return_note', 'Zou niet opgeslagen moeten worden')
        ->set('receiverName', 'Jan Jansen')
        ->set('signature', 'data:image/png;base64,iVBORw0KGgo=')
        ->call('saveDelivery');

    $deliveryItem = DeliveryItem::query()
        ->where('order_item_id', $orderItem->id)
        ->first();

    expect($deliveryItem->return_note)->toBeNull();
});

it('can navigate back to a delivered stop and forward again', function () {
    Storage::fake(UploadStorage::diskName());

    $driver = User::factory()->driver()->create();
    $route = Route::factory()->loadingCompleted()->create([
        'driver_id' => $driver->id,
    ]);

    $firstOrder = Order::factory()->placed()->for(Customer::factory())->create();
    OrderItem::factory()->for($firstOrder)->create(['quantity' => 5]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $firstOrder->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    $secondOrder = Order::factory()->placed()->for(Customer::factory())->create();
    OrderItem::factory()->for($secondOrder)->create(['quantity' => 3]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $secondOrder->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);

    Livewire::actingAs($driver)
        ->withQueryParams(['routeId' => $route->id])
        ->test(DriverDeliveryPhase::class)
        ->assertSet('currentStopIndex', 0)
        ->set('receiverName', 'Jan Jansen')
        ->set('signature', 'data:image/png;base64,iVBORw0KGgo=')
        ->call('saveDelivery')
        ->assertSet('currentStopIndex', 1)
        ->call('previousStop')
        ->assertSet('currentStopIndex', 0)
        ->call('nextStop')
        ->assertSet('currentStopIndex', 1);
});

it('can resume a skipped stop and complete the delivery', function () {
    Storage::fake(UploadStorage::diskName());

    $driver = User::factory()->driver()->create();
    $route = Route::factory()->loadingCompleted()->create([
        'driver_id' => $driver->id,
    ]);

    $firstOrder = Order::factory()->placed()->for(Customer::factory())->create();
    $firstItem = OrderItem::factory()->for($firstOrder)->create(['quantity' => 5]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $firstOrder->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => Order::factory()->placed()->for(Customer::factory())->create()->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);

    Livewire::actingAs($driver)
        ->withQueryParams(['routeId' => $route->id])
        ->test(DriverDeliveryPhase::class)
        ->call('skipStop')
        ->assertSet('currentStopIndex', 1)
        ->call('previousStop')
        ->assertSet('currentStopIndex', 0)
        ->call('resumeStop')
        ->assertNotified()
        ->set('receiverName', 'Jan Jansen')
        ->set('signature', 'data:image/png;base64,iVBORw0KGgo=')
        ->call('saveDelivery')
        ->assertNotified();

    expect(RouteStop::query()->where('order_id', $firstOrder->id)->first()->status)
        ->toBe(RouteStopStatus::DELIVERED);

    expect(DeliveryItem::query()->where('order_item_id', $firstItem->id)->exists())->toBeTrue();
});
