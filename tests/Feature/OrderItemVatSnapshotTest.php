<?php

use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Services\OrderItemSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createStandardProduct(VatCategory $vatCategory = VatCategory::High): Product
{
    $product = Product::factory()->create([
        'product_type' => ProductType::Standard,
        'vat_category' => $vatCategory,
    ]);

    ProductPackaging::factory()->for($product)->create(['is_default' => true]);
    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => 5.0,
        'is_default' => true,
    ]);

    return $product->fresh(['activePackagings', 'activeProductSuppliers.supplier']);
}

function createWholeChickenProductForVat(VatCategory $vatCategory = VatCategory::High): Product
{
    $product = Product::factory()->create([
        'product_type' => ProductType::WholeChicken,
        'allows_loading_substitute' => true,
        'vat_category' => $vatCategory,
    ]);

    ProductGramVariant::factory()->for($product)->create(['is_default' => true]);
    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => 6.5,
        'is_default' => true,
    ]);

    return $product->fresh(['activeGramVariants', 'activeProductSuppliers.supplier']);
}

it('snapshots 21 percent vat for high vat standard products', function () {
    $product = createStandardProduct(VatCategory::High);
    $packaging = $product->defaultPackaging();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromStandardLine(
        $product,
        $packaging,
        $productSupplier,
        2,
    );

    expect($snapshot['vat_rate'])->toBe(21.0);
});

it('snapshots 9 percent vat for low vat standard products', function () {
    $product = createStandardProduct(VatCategory::Low);
    $packaging = $product->defaultPackaging();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromStandardLine(
        $product,
        $packaging,
        $productSupplier,
        1,
    );

    expect($snapshot['vat_rate'])->toBe(9.0);
});

it('snapshots vat rate on whole chicken order lines', function () {
    $product = createWholeChickenProductForVat(VatCategory::Low);
    $gramVariant = $product->defaultGramVariant();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromWholeChickenLine(
        $product,
        $gramVariant,
        $productSupplier,
        1,
    );

    expect($snapshot['vat_rate'])->toBe(9.0);
});

it('snapshots zero vat for vat exempt customers regardless of product category', function () {
    $customer = Customer::factory()->create(['is_vat_exempt' => true]);
    $product = createStandardProduct(VatCategory::High);
    $packaging = $product->defaultPackaging();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromStandardLine(
        $product,
        $packaging,
        $productSupplier,
        1,
        $customer,
    );

    expect($snapshot['vat_rate'])->toBe(0.0);
});

it('persists vat_rate on order items when created from a snapshot', function () {
    $product = createStandardProduct(VatCategory::Low);
    $packaging = $product->defaultPackaging();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromStandardLine(
        $product,
        $packaging,
        $productSupplier,
        1,
    );

    $orderItem = OrderItem::factory()->create($snapshot);

    expect((float) $orderItem->fresh()->vat_rate)->toBe(9.0);
});
