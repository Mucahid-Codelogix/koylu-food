<?php

use App\Enums\DeliveryStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Filament\Driver\Pages\DriverDeliveryPhase;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;

it('slaat de levering op ook als deliveryData onvolledige of stray regels bevat', function () {
    Storage::fake(UploadStorage::diskName());

    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
        'loading_completed_at' => now(),
    ]);

    $order = Order::factory()->create();
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    $product = Product::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'box_weight_kg' => 2,
        'price_per_kg' => 10,
        'vat_rate' => 21,
    ]);

    // Tweede openstaande stop, zodat saveDelivery na stop 1 doorschuift
    // i.p.v. te redirecten naar het dashboard (dat in de test niet bestaat).
    $secondOrder = Order::factory()->create();
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $secondOrder->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);
    OrderItem::factory()->create([
        'order_id' => $secondOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'box_weight_kg' => 2,
        'price_per_kg' => 10,
        'vat_rate' => 21,
    ]);

    $this->actingAs($driver);

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    // Simuleer wat Livewire soms achterlaat: een orderregel zonder 'is_missed'
    // plus een stray sleutel die niet bij een orderregel hoort.
    $component->set('deliveryData', [
        $item->id => ['ordered_quantity' => 3, 'delivered_quantity' => 3],
        999999 => ['foo' => 'bar'],
    ]);
    $component->set('receiverName', 'Jan Jansen');
    $component->set('signature', 'data:image/png;base64,'.base64_encode('x'));
    $component->set('driverSignature', 'data:image/png;base64,'.base64_encode('x'));

    $component->call('saveDelivery');

    $delivery = Delivery::where('order_id', $order->id)->first();

    expect($delivery)->not->toBeNull();
    expect($delivery->status)->toBe(DeliveryStatus::DELIVERED);

    $deliveryItem = DeliveryItem::where('order_item_id', $item->id)->first();
    expect($deliveryItem)->not->toBeNull();
    expect((float) $deliveryItem->delivered_quantity)->toBe(3.0);

    // De stray sleutel heeft geen leveringsregel aangemaakt.
    expect(DeliveryItem::where('delivery_id', $delivery->id)->count())->toBe(1);
});
