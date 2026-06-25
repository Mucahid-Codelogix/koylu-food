<?php

use App\Enums\PackagingType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toont generieke verpakking bij elke leverancier', function () {
    $product = Product::factory()->create();
    $supplierA = ProductSupplier::factory()->for($product)->create();
    $supplierB = ProductSupplier::factory()->for($product)->create();
    $generiek = ProductPackaging::factory()->for($product)->create();

    expect(ProductPackaging::forProductSupplier($supplierA->id)->pluck('id'))->toContain($generiek->id);
    expect(ProductPackaging::forProductSupplier($supplierB->id)->pluck('id'))->toContain($generiek->id);
});

it('beperkt subset-verpakking tot gekoppelde leveranciers', function () {
    $product = Product::factory()->create();
    $supplierA = ProductSupplier::factory()->for($product)->create();
    $supplierB = ProductSupplier::factory()->for($product)->create();
    $supplierC = ProductSupplier::factory()->for($product)->create();

    $pkg = ProductPackaging::factory()->for($product)->create();
    $pkg->productSuppliers()->attach([$supplierA->id, $supplierC->id]);

    expect(ProductPackaging::forProductSupplier($supplierA->id)->pluck('id'))->toContain($pkg->id);
    expect(ProductPackaging::forProductSupplier($supplierC->id)->pluck('id'))->toContain($pkg->id);
    expect(ProductPackaging::forProductSupplier($supplierB->id)->pluck('id'))->not->toContain($pkg->id);
});

it('filtert verpakkingen in de shop op gekozen leverancier', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);

    $product = Product::factory()->create();
    $supplierA = Supplier::factory()->create(['name' => 'Leverancier A']);
    $supplierB = Supplier::factory()->create(['name' => 'Leverancier B']);

    $offerA = ProductSupplier::factory()->for($product)->for($supplierA)->create([
        'is_default' => true,
    ]);
    $offerB = ProductSupplier::factory()->for($product)->for($supplierB)->create([
        'is_default' => false,
    ]);

    $generiek = ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
        'is_default' => true,
    ]);

    $restricted = ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Tray,
        'weight_kg' => 1.5,
        'is_default' => false,
    ]);
    $restricted->productSuppliers()->attach($offerA->id);

    $cartKeyA = "{$product->id}-pk{$restricted->id}-ps{$offerA->id}";
    $cartKeyB = "{$product->id}-pk{$generiek->id}-ps{$offerB->id}";

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('selections.'.$product->id.'.supplier_id', $offerA->id)
        ->set('selections.'.$product->id.'.packaging_id', $restricted->id)
        ->call('addToCart', $product->id)
        ->assertSet('cart.'.$cartKeyA.'.product_packaging_id', $restricted->id);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('selections.'.$product->id.'.supplier_id', $offerB->id)
        ->set('selections.'.$product->id.'.packaging_id', $restricted->id)
        ->call('addToCart', $product->id)
        ->assertSet('cart.'.$cartKeyB.'.product_packaging_id', $generiek->id);
});

it('houdt prijsberekening ongewijzigd ongeacht leverancierskoppeling', function () {
    $product = Product::factory()->create();
    $supplierA = ProductSupplier::factory()->for($product)->create(['price_per_kg' => 9.80]);
    $supplierB = ProductSupplier::factory()->for($product)->create(['price_per_kg' => 9.80]);

    $packaging = ProductPackaging::factory()->for($product)->create(['weight_kg' => 10]);
    $packaging->productSuppliers()->attach($supplierA->id);

    $pricing = app(ProductPricingService::class);

    expect($pricing->unitPricePerPackaging(null, $supplierA, $packaging))->toBe(98.0)
        ->and($pricing->unitPricePerPackaging(null, $supplierB, $packaging))->toBe(98.0)
        ->and($pricing->lineSubtotal(null, $supplierA, $packaging, 5))->toBe(490.0)
        ->and($pricing->lineSubtotal(null, $supplierB, $packaging, 5))->toBe(490.0);
});

it('toont gekoppelde verpakkingen op leverancier via linkedPackagings query', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $offer = ProductSupplier::factory()->for($product)->for($supplier)->create();

    $linked = ProductPackaging::factory()->for($product)->create([
        'label' => 'Exclusief',
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
    ]);
    $generic = ProductPackaging::factory()->for($product)->create([
        'label' => 'Generiek',
        'packaging_type' => PackagingType::Tray,
        'weight_kg' => 1.5,
    ]);
    $linked->productSuppliers()->attach($offer->id);

    $ids = $supplier->linkedPackagings()->pluck('id');

    expect($ids)->toContain($linked->id)
        ->and($ids)->not->toContain($generic->id);
});

it('verwijdert koppelrijen bij verwijderen van product supplier en valt terug op generiek', function () {
    $product = Product::factory()->create();
    $supplierA = ProductSupplier::factory()->for($product)->create();
    $supplierB = ProductSupplier::factory()->for($product)->create();

    $pkg = ProductPackaging::factory()->for($product)->create();
    $pkg->productSuppliers()->attach([$supplierA->id, $supplierB->id]);

    expect($pkg->productSuppliers)->toHaveCount(2);

    $supplierA->delete();

    $pkg->refresh();

    expect($pkg->productSuppliers)->toHaveCount(1)
        ->and($pkg->productSuppliers->first()->is($supplierB))->toBeTrue()
        ->and($pkg->isAvailableForProductSupplier($supplierB->id))->toBeTrue();

    $supplierB->delete();

    $pkg->refresh();

    expect($pkg->productSuppliers)->toHaveCount(0)
        ->and($pkg->isAvailableForProductSupplier($supplierA->id))->toBeTrue()
        ->and($pkg->isAvailableForProductSupplier($supplierB->id))->toBeTrue();
});
