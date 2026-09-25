<?php

use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductSupplier;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderRepeatService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function wholeChickenWithTwoSuppliers(): Product
{
    $product = Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create(['name' => 'Slachthuis A']))->create([
        'price_per_kg' => 6.5,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create(['name' => 'Slachthuis B']))->create([
        'price_per_kg' => 6.5,
        'is_default' => false,
    ]);

    return $product->fresh(['activeGramVariants', 'activeProductSuppliers.supplier']);
}

it('beschouwt een variant zonder koppeling als beschikbaar bij elke leverancier', function () {
    $product = wholeChickenWithTwoSuppliers();
    $offerA = $product->activeProductSuppliers->first();
    $offerB = $product->activeProductSuppliers->last();

    $variant = ProductGramVariant::factory()->for($product)->create(['weight_grams' => 1200]);

    expect($variant->isAvailableForProductSupplier($offerA->id))->toBeTrue()
        ->and($variant->isAvailableForProductSupplier($offerB->id))->toBeTrue();
});

it('beperkt een gekoppelde variant tot de gekozen leveranciers', function () {
    $product = wholeChickenWithTwoSuppliers();
    $offerA = $product->activeProductSuppliers->first();
    $offerB = $product->activeProductSuppliers->last();

    $variant = ProductGramVariant::factory()->for($product)->create(['weight_grams' => 1300]);
    $variant->productSuppliers()->attach($offerA->id);
    $variant->load('productSuppliers');

    expect($variant->isAvailableForProductSupplier($offerA->id))->toBeTrue()
        ->and($variant->isAvailableForProductSupplier($offerB->id))->toBeFalse();
});

it('toont in de shop alleen de gramvarianten van de gekozen leverancier', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);

    $product = wholeChickenWithTwoSuppliers();
    $offerA = $product->activeProductSuppliers->first();
    $offerB = $product->activeProductSuppliers->last();

    $generiek = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'is_default' => true,
        'label' => 'Variant generiek',
    ]);

    $alleenA = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1300,
        'pieces_per_box' => 10,
        'box_weight_kg' => 13,
        'is_default' => false,
        'label' => 'Variant alleen A',
    ]);
    $alleenA->productSuppliers()->attach($offerA->id);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('selections.'.$product->id.'.supplier_id', $offerA->id)
        ->assertSee('Variant alleen A')
        ->set('selections.'.$product->id.'.supplier_id', $offerB->id)
        ->assertDontSee('Variant alleen A')
        ->assertSee('Variant generiek');

    expect($generiek->fresh()->isAvailableForProductSupplier($offerB->id))->toBeTrue();
});

it('valt terug op een toegestane variant als de klant een variant van een andere leverancier kiest', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);

    $product = wholeChickenWithTwoSuppliers();
    $offerA = $product->activeProductSuppliers->first();
    $offerB = $product->activeProductSuppliers->last();

    $generiek = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'is_default' => true,
    ]);

    $alleenA = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1300,
        'pieces_per_box' => 10,
        'box_weight_kg' => 13,
        'is_default' => false,
    ]);
    $alleenA->productSuppliers()->attach($offerA->id);

    $cartKey = "{$product->id}-gv{$generiek->id}-ps{$offerB->id}";

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('selections.'.$product->id.'.supplier_id', $offerB->id)
        ->set('selections.'.$product->id.'.gram_variant_id', $alleenA->id)
        ->call('addToCart', $product->id)
        ->assertSet('cart.'.$cartKey.'.product_gram_variant_id', $generiek->id);
});

it('slaat bij opnieuw bestellen een variant over die de leverancier niet meer levert', function () {
    $customer = Customer::factory()->create();
    $product = wholeChickenWithTwoSuppliers();
    $offerA = $product->activeProductSuppliers->first();
    $offerB = $product->activeProductSuppliers->last();

    $variant = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1300,
        'pieces_per_box' => 10,
        'box_weight_kg' => 13,
        'is_default' => true,
    ]);
    $variant->productSuppliers()->attach($offerA->id);

    $order = Order::factory()->create(['customer_id' => $customer->id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_gram_variant_id' => $variant->id,
        'product_supplier_id' => $offerB->id,
        'product_name' => $product->name,
        'quantity' => 1,
    ]);

    $result = app(OrderRepeatService::class)->placeCopy($order->fresh('items'));

    expect($result['order'])->toBeNull()
        ->and($result['skipped'])->toBe(['Hele Kip']);
});

it('biedt de chauffeur alleen varianten aan die de leverancier van de regel levert', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
    ]);

    $product = Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
        'allows_loading_substitute' => true,
    ]);

    $offerA = ProductSupplier::factory()->for($product)->for(Supplier::factory()->create(['name' => 'Slachthuis A']))->create();
    $offerB = ProductSupplier::factory()->for($product)->for(Supplier::factory()->create(['name' => 'Slachthuis B']))->create();

    $besteld = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'label' => 'Variant besteld',
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
    ]);

    $alleenA = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1300,
        'label' => 'Variant alleen A',
        'pieces_per_box' => 10,
        'box_weight_kg' => 13,
    ]);
    $alleenA->productSuppliers()->attach($offerA->id);

    $order = Order::factory()->placed()->for(Customer::factory())->create();
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    $item = OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $besteld->id,
        'product_supplier_id' => $offerB->id,
        'quantity' => 1,
        'box_weight_kg' => 12,
        'ordered_pieces' => 10,
        'ordered_total_weight_kg' => 12,
    ]);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->assertSee('Variant besteld')
        ->assertDontSee('Variant alleen A')
        ->set("loadingData.{$item->id}.loaded_gram_variant_id", (string) $alleenA->id)
        ->set("loadingData.{$item->id}.substitution_reason", 'Niet op voorraad')
        ->call('saveLoadedVariant', $item->id);

    expect($item->fresh()->loaded_gram_variant_id)->toBeNull();
});
