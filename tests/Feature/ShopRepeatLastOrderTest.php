<?php

use App\Enums\OrderStatus;
use App\Enums\PackagingType;
use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function shopCustomerUser(): User
{
    $customer = Customer::factory()->create();

    return User::factory()->create(['customer_id' => $customer->id]);
}

function standardShopProduct(float $weightKg = 10, float $pricePerKg = 10): Product
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

function wholeChickenShopProduct(float $pricePerKg = 6.5): Product
{
    $product = Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
    ]);

    ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => $pricePerKg,
        'is_default' => true,
    ]);

    return $product->fresh(['activeGramVariants', 'activeProductSuppliers.supplier']);
}

it('puts the lines of the last order back in the cart', function () {
    $user = shopCustomerUser();
    $product = standardShopProduct(weightKg: 10, pricePerKg: 10);
    $packaging = $product->activePackagings->first();
    $productSupplier = $product->activeProductSuppliers->first();

    $order = Order::factory()->create(['customer_id' => $user->customer_id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $packaging->id,
        'product_supplier_id' => $productSupplier->id,
        'product_name' => $product->name,
        'quantity' => 3,
    ]);

    $component = Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder')
        ->assertSet('cartOpen', true);

    $line = collect($component->get('cart'))->first();

    expect($component->get('cart'))->toHaveCount(1)
        ->and((float) $line['quantity'])->toBe(3.0)
        ->and((float) $component->get('total'))->toBe(300.0);
});

it('repeats a whole chicken line with the ordered gram variant', function () {
    $user = shopCustomerUser();
    $product = wholeChickenShopProduct(pricePerKg: 6.5);
    $gramVariant = $product->activeGramVariants->first();
    $productSupplier = $product->activeProductSuppliers->first();

    $order = Order::factory()->create(['customer_id' => $user->customer_id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_gram_variant_id' => $gramVariant->id,
        'product_supplier_id' => $productSupplier->id,
        'product_name' => $product->name,
        'quantity' => 2,
    ]);

    $component = Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder');

    $line = collect($component->get('cart'))->first();

    expect($line['is_whole_chicken'])->toBeTrue()
        ->and($line['product_gram_variant_id'])->toBe($gramVariant->id)
        ->and((float) $line['quantity'])->toBe(2.0)
        ->and((float) $component->get('total'))->toBe(156.0);
});

it('uses the current price instead of the price of the previous order', function () {
    $user = shopCustomerUser();
    $product = standardShopProduct(weightKg: 10, pricePerKg: 10);
    $packaging = $product->activePackagings->first();
    $productSupplier = $product->activeProductSuppliers->first();

    $order = Order::factory()->create(['customer_id' => $user->customer_id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $packaging->id,
        'product_supplier_id' => $productSupplier->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 50,
        'subtotal' => 50,
    ]);

    $productSupplier->update(['price_per_kg' => 12]);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder')
        ->assertSet('total', 120.0);
});

it('skips lines that are no longer available', function () {
    $user = shopCustomerUser();
    $available = standardShopProduct(weightKg: 10, pricePerKg: 10);
    $unavailable = standardShopProduct(weightKg: 5, pricePerKg: 10);
    $unavailable->update(['is_active' => false]);

    $order = Order::factory()->create(['customer_id' => $user->customer_id]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $available->id,
        'product_packaging_id' => $available->activePackagings->first()->id,
        'product_supplier_id' => $available->activeProductSuppliers->first()->id,
        'product_name' => $available->name,
        'quantity' => 1,
    ]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $unavailable->id,
        'product_packaging_id' => $unavailable->activePackagings->first()->id,
        'product_supplier_id' => $unavailable->activeProductSuppliers->first()->id,
        'product_name' => $unavailable->name,
        'quantity' => 1,
    ]);

    $component = Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder');

    expect($component->get('cart'))->toHaveCount(1);
});

it('skips a line whose supplier offer is no longer active', function () {
    $user = shopCustomerUser();
    $product = standardShopProduct();
    $productSupplier = $product->activeProductSuppliers->first();

    $order = Order::factory()->create(['customer_id' => $user->customer_id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $product->activePackagings->first()->id,
        'product_supplier_id' => $productSupplier->id,
        'product_name' => $product->name,
        'quantity' => 1,
    ]);

    $productSupplier->update(['is_active' => false]);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder')
        ->assertSet('cart', [])
        ->assertSet('cartOpen', false);
});

it('ignores cancelled orders when looking for the last order', function () {
    $user = shopCustomerUser();
    $product = standardShopProduct(weightKg: 10, pricePerKg: 10);

    $olderOrder = Order::factory()->create([
        'customer_id' => $user->customer_id,
        'order_date' => now()->subDays(3),
    ]);
    OrderItem::factory()->for($olderOrder)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $product->activePackagings->first()->id,
        'product_supplier_id' => $product->activeProductSuppliers->first()->id,
        'product_name' => $product->name,
        'quantity' => 4,
    ]);

    $cancelledOrder = Order::factory()->create([
        'customer_id' => $user->customer_id,
        'status' => OrderStatus::CANCELLED,
        'order_date' => now(),
    ]);
    OrderItem::factory()->for($cancelledOrder)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $product->activePackagings->first()->id,
        'product_supplier_id' => $product->activeProductSuppliers->first()->id,
        'product_name' => $product->name,
        'quantity' => 1,
    ]);

    $component = Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('repeatLastOrder');

    $line = collect($component->get('cart'))->first();

    expect((float) $line['quantity'])->toBe(4.0);
});

it('does not show the repeat button without a previous order', function () {
    $user = shopCustomerUser();
    standardShopProduct();

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->assertDontSee('Herhaal vorige bestelling');
});
