<?php

use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Filament\Driver\Pages\DriverDeliveryPhase;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\OrderItemLoadingService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

function loadingRouteWithItem(array $itemAttributes = []): array
{
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
    ]);

    $product = Product::factory()->create(['name' => 'Kipfilet']);
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Kipfilet',
        'product_type' => ProductType::Standard,
        'product_gram_variant_id' => null,
        'unit' => 'Doos 10.000 kg',
        'quantity' => 3,
        'ordered_pieces' => null,
        'ordered_total_weight_kg' => 30.0,
        ...$itemAttributes,
    ]);

    return [$driver, $route, $item];
}

it('slaat een gecorrigeerd aantal dozen op tijdens het laden', function () {
    [$driver, $route, $item] = loadingRouteWithItem();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);

    $component->set("loadingData.{$item->id}.loaded_quantity", '2');
    $component->call('finishLoading');

    expect((float) $item->fresh()->loaded_packaging_quantity)->toBe(2.0);
});

it('laat het aantal leeg wanneer er geladen is zoals besteld', function () {
    [$driver, $route, $item] = loadingRouteWithItem();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);

    $component->set("loadingData.{$item->id}.loaded_quantity", '3');
    $component->call('finishLoading');

    expect($item->fresh()->loaded_packaging_quantity)->toBeNull();
});

it('herberekent stuks en gewicht van hele kip bij een aantalcorrectie', function () {
    $product = Product::factory()->create(['name' => 'Hele kip']);
    $variant = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12.35,
    ]);

    [$driver, $route, $item] = loadingRouteWithItem([
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $variant->id,
        'unit' => 'Doos 1200 g',
        'quantity' => 4,
        'ordered_pieces' => 40,
        'ordered_total_weight_kg' => 49.4,
    ]);

    app(OrderItemLoadingService::class)->recordLoadedQuantity($item->fresh(), 2.0);

    $item->refresh();

    expect((float) $item->loaded_packaging_quantity)->toBe(2.0)
        ->and((float) $item->loaded_pieces)->toBe(20.0)
        ->and((float) $item->loaded_total_weight_kg)->toBe(24.7);
});

it('vult bij afleveren het geladen aantal voor met een correctienotitie', function () {
    [$driver, $route, $item] = loadingRouteWithItem();

    app(OrderItemLoadingService::class)->recordLoadedQuantity($item, 2.0);

    $route->update(['loading_completed_at' => now()]);

    $this->actingAs($driver);

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    $deliveryData = $component->get('deliveryData')[$item->id];

    expect((float) $deliveryData['delivered_quantity'])->toBe(2.0)
        ->and((float) $deliveryData['ordered_quantity'])->toBe(3.0)
        ->and($deliveryData['return_note'])->toContain('origineel 3, geladen 2');
});

it('laat de pijltjes bij het geladen aantal per hele verpakking verspringen', function () {
    [$driver, $route, $item] = loadingRouteWithItem();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->assertSeeHtml('id="loaded-qty-'.$item->id.'"')
        ->assertSeeHtml('step="any"')
        ->assertDontSeeHtml('step="0.0');
});

it('accepteert nog steeds een handmatig getypt kommagetal als geladen aantal', function () {
    [$driver, $route, $item] = loadingRouteWithItem();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);

    $component->set("loadingData.{$item->id}.loaded_quantity", '2,5');
    $component->call('finishLoading');

    expect((float) $item->fresh()->loaded_packaging_quantity)->toBe(2.5);
});
