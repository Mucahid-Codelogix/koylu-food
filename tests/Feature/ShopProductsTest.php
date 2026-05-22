<?php

use App\Enums\PackagingType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createShopProduct(float $weightKg = 10, float $pricePerKg = 9.8): Product
{
    $product = Product::factory()->create(['name' => 'Kipfilet']);

    ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => $weightKg,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => $pricePerKg,
        'is_default' => true,
    ]);

    return $product->fresh(['activePackagings', 'activeProductSuppliers.supplier']);
}

it('calculates cart total as price per kg times packaging weight times quantity', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = createShopProduct(weightKg: 10, pricePerKg: 9.8);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->assertSet('total', 98.0);
});

it('uses composite cart keys for different packagings', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = Product::factory()->create();

    ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
        'is_default' => true,
    ]);

    ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Tray,
        'weight_kg' => 1.5,
        'is_default' => false,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => 10,
        'is_default' => true,
    ]);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->set('selections.'.$product->id.'.packaging_id', $product->packagings()->where('weight_kg', 1.5)->value('id'))
        ->call('addToCart', $product->id)
        ->assertCount('cart', 2);
});

it('filters products by supplier via product suppliers pivot', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);

    $supplierA = Supplier::factory()->create(['name' => 'Leverancier A']);
    $supplierB = Supplier::factory()->create(['name' => 'Leverancier B']);

    $productA = Product::factory()->create(['name' => 'Vleugel A']);
    ProductPackaging::factory()->for($productA)->create(['weight_kg' => 10, 'is_default' => true]);
    ProductSupplier::factory()->for($productA)->for($supplierA)->create(['price_per_kg' => 5, 'is_default' => true]);

    $productB = Product::factory()->create(['name' => 'Vleugel B']);
    ProductPackaging::factory()->for($productB)->create(['weight_kg' => 10, 'is_default' => true]);
    ProductSupplier::factory()->for($productB)->for($supplierB)->create(['price_per_kg' => 6, 'is_default' => true]);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('selectedSupplier', $supplierA->id)
        ->assertSee('Vleugel A')
        ->assertDontSee('Vleugel B');
});

it('clears search and supplier filters', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $supplier = Supplier::factory()->create();
    createShopProduct();

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->set('search', 'Kip')
        ->set('selectedSupplier', $supplier->id)
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('selectedSupplier', null);
});

it('opens confirmation modal before placing order', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = createShopProduct();

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->call('requestPlaceOrder')
        ->assertSet('confirmOrderModalOpen', true)
        ->call('cancelPlaceOrder')
        ->assertSet('confirmOrderModalOpen', false);
});

it('increments product quantity before adding to cart', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = createShopProduct();

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->assertSet('quantities.'.$product->id, 1)
        ->call('incrementProductQuantity', $product->id)
        ->assertSet('quantities.'.$product->id, 2);
});
