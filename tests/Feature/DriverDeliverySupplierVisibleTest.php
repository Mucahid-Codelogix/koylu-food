<?php

use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Filament\Driver\Pages\DriverDeliveryPhase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;

it('toont de leverancier per productregel in het leveringsproces', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
        'loading_completed_at' => now(),
    ]);

    $product = Product::factory()->create(['name' => 'Hele kip']);
    $order = Order::factory()->create();

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'unit' => 'Doos 1000 g',
        'quantity' => 2,
        'supplier_name' => 'Pluimveehandel Noord',
    ]);

    $this->actingAs($driver);

    $component = Testable::create(
        DriverDeliveryPhase::class,
        [],
        ['routeId' => $route->id],
    );

    $deliveryData = $component->get('deliveryData');

    expect($deliveryData[$item->id]['supplier_name'])->toBe('Pluimveehandel Noord');
});
