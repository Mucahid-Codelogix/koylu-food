<?php

use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Route, 2: OrderItem, 3: ProductGramVariant}
 */
function wholeChickenLoadingRoute(): array
{
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
    ]);

    $product = Product::factory()->create([
        'name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'allows_loading_substitute' => true,
    ]);

    $ordered = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1200,
        'label' => '1200 g',
        'pieces_per_box' => 10,
        'box_weight_kg' => 12.35,
    ]);

    $alternative = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1300,
        'label' => '1300 g',
        'pieces_per_box' => 10,
        'box_weight_kg' => 13.0,
    ]);

    $order = Order::factory()->placed()->for(Customer::factory())->create();

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $ordered->id,
        'unit' => 'Doos 1200 g',
        'quantity' => 2,
        'box_weight_kg' => 12.35,
        'ordered_pieces' => 20,
        'ordered_total_weight_kg' => 24.7,
    ]);

    return [$driver, $route, $item, $alternative];
}

it('slaat een gewijzigde gramvariant ook op bij klaar met laden, zonder op de knop te drukken', function () {
    [$driver, $route, $item, $alternative] = wholeChickenLoadingRoute();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->set("loadingData.{$item->id}.loaded_gram_variant_id", (string) $alternative->id)
        ->set("loadingData.{$item->id}.substitution_reason", 'Niet op voorraad')
        ->call('finishLoading');

    $item->refresh();

    expect($item->loaded_gram_variant_id)->toBe($alternative->id)
        ->and((float) $item->loaded_box_weight_kg)->toBe(13.0)
        ->and($item->loading_substitution_reason)->toBe('Niet op voorraad')
        ->and($item->billableWeightKg(2))->toBe(26.0);
});

it('laat de chauffeur niet verder als de reden bij een andere variant ontbreekt', function () {
    [$driver, $route, $item, $alternative] = wholeChickenLoadingRoute();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->set("loadingData.{$item->id}.loaded_gram_variant_id", (string) $alternative->id)
        ->set("loadingData.{$item->id}.substitution_reason", '')
        ->call('finishLoading')
        ->assertNoRedirect();

    expect($item->fresh()->loaded_gram_variant_id)->toBeNull()
        ->and($route->fresh()->loading_completed_at)->toBeNull();
});

it('factureert het ingevulde werkelijke gewicht na het laden en toont dat aan de chauffeur', function () {
    [$driver, $route, $item] = wholeChickenLoadingRoute();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->assertSee('Ingevuld gewicht wordt gefactureerd')
        ->assertSee('24,70 kg volgens variant')
        ->set("loadingData.{$item->id}.actual_weight_kg", '25,6')
        ->call('finishLoading');

    expect($item->fresh()->billableWeightKg(2))->toBe(25.6);
});
