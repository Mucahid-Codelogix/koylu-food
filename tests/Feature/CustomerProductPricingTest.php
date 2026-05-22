<?php

use App\Enums\PackagingType;
use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createPricedProductOffer(
    string $supplierName,
    float $standardPricePerKg,
    ?Product $product = null,
    bool $isDefault = true,
): ProductSupplier {
    $product ??= Product::factory()->create(['name' => 'Kipfilet']);

    if (! $product->packagings()->exists()) {
        ProductPackaging::factory()->for($product)->create([
            'packaging_type' => PackagingType::Box,
            'weight_kg' => 10,
            'is_default' => true,
        ]);
    }

    $supplier = Supplier::factory()->create(['name' => $supplierName]);

    return ProductSupplier::factory()->for($product)->for($supplier)->create([
        'price_per_kg' => $standardPricePerKg,
        'is_default' => $isDefault,
        'is_active' => true,
    ]);
}

it('uses customer price per product supplier when configured', function () {
    $customer = Customer::factory()->create();
    $offer = createPricedProductOffer('Leverancier A', 9.80);

    CustomerProductPrice::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $offer->product_id,
        'product_supplier_id' => $offer->id,
        'price' => 8.50,
    ]);

    $pricing = app(ProductPricingService::class);

    expect($pricing->pricePerKg($customer, $offer))->toBe(8.5);
});

it('falls back to standard price when no customer price exists for that supplier', function () {
    $customer = Customer::factory()->create();
    $offer = createPricedProductOffer('Leverancier B', 10.50);

    $pricing = app(ProductPricingService::class);

    expect($pricing->pricePerKg($customer, $offer))->toBe(10.5);
});

it('supports different customer prices per supplier for the same product', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->create(['name' => 'Kipfilet']);
    $offerA = createPricedProductOffer('Leverancier A', 9.80, $product, isDefault: true);
    $offerB = createPricedProductOffer('Leverancier B', 10.50, $product, isDefault: false);

    CustomerProductPrice::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $offerA->product_id,
        'product_supplier_id' => $offerA->id,
        'price' => 8.50,
    ]);

    CustomerProductPrice::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $offerB->product_id,
        'product_supplier_id' => $offerB->id,
        'price' => 9.25,
    ]);

    $pricing = app(ProductPricingService::class);

    expect($pricing->pricePerKg($customer, $offerA))->toBe(8.5)
        ->and($pricing->pricePerKg($customer, $offerB))->toBe(9.25);
});

it('shows customer-specific shop prices for all users of the same customer', function () {
    $customer = Customer::factory()->create();
    $userA = User::factory()->create(['customer_id' => $customer->id]);
    $userB = User::factory()->create(['customer_id' => $customer->id]);

    $offer = createPricedProductOffer('Leverancier A', 9.80);

    CustomerProductPrice::factory()->create([
        'customer_id' => $customer->id,
        'product_id' => $offer->product_id,
        'product_supplier_id' => $offer->id,
        'price' => 7.90,
    ]);

    $product = $offer->product->fresh(['activePackagings', 'activeProductSuppliers.supplier']);

    Livewire::actingAs($userA)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->assertSet('total', 79.0);

    Livewire::actingAs($userB)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->assertSet('total', 79.0);
});

it('shows standard shop price for another customer without custom pricing', function () {
    $customerWithPrice = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $otherCustomer->id]);

    $offer = createPricedProductOffer('Leverancier A', 9.80);

    CustomerProductPrice::factory()->create([
        'customer_id' => $customerWithPrice->id,
        'product_id' => $offer->product_id,
        'product_supplier_id' => $offer->id,
        'price' => 7.90,
    ]);

    $product = $offer->product->fresh(['activePackagings', 'activeProductSuppliers.supplier']);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->assertSet('total', 98.0);
});
