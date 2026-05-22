<?php

use App\Enums\PackagingType;
use App\Models\Product;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores multiple packagings per unique product', function () {
    $product = Product::factory()->create(['name' => 'Kipfilet']);

    $box = ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
        'is_default' => true,
    ]);

    $tray = ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Tray,
        'weight_kg' => 1.5,
        'is_default' => false,
    ]);

    expect($product->packagings)->toHaveCount(2)
        ->and($product->defaultPackaging()?->is($box))->toBeTrue()
        ->and($tray->weight_kg)->toBe('1.500');
});

it('stores multiple suppliers with price per kg on one product', function () {
    $product = Product::factory()->create(['name' => 'Kippenvleugels']);
    $supplierA = Supplier::create(['name' => 'Leverancier A', 'is_active' => true]);
    $supplierB = Supplier::create(['name' => 'Leverancier B', 'is_active' => true]);

    ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
    ]);

    $offerA = ProductSupplier::factory()->for($product)->for($supplierA)->create([
        'price_per_kg' => 2.40,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for($supplierB)->create([
        'price_per_kg' => 2.55,
        'is_default' => false,
    ]);

    expect($product->productSuppliers)->toHaveCount(2)
        ->and($product->defaultProductSupplier()?->is($offerA))->toBeTrue()
        ->and($product->suppliers)->toHaveCount(2);
});

it('calculates line subtotal as price per kg times packaging weight times quantity', function () {
    $product = Product::factory()->create();
    $packaging = ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => 10,
    ]);
    $productSupplier = ProductSupplier::factory()->for($product)->create([
        'price_per_kg' => 9.80,
    ]);

    $subtotal = $product->calculateLineSubtotal($packaging, $productSupplier, 5);

    expect($subtotal)->toBe('490.00');
});

it('calculates packaging unit price from supplier price per kg and packaging weight', function () {
    $product = Product::factory()->create();
    $packaging = ProductPackaging::factory()->for($product)->create(['weight_kg' => 2.5]);
    $productSupplier = ProductSupplier::factory()->for($product)->create(['price_per_kg' => 8.40]);

    expect($productSupplier->calculatePackagingPrice($packaging))->toBe('21.0000');
});
