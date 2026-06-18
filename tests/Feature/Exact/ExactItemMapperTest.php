<?php

use App\Enums\VatCategory;
use App\Models\Product;
use App\Services\Exact\ExactItemMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('maps a product to an exact item payload', function () {
    config([
        'exact.item.unit' => 'kg',
        'exact.item.code_prefix' => 'KOYLU',
        'exact.item.vat_codes.low' => '1',
        'exact.item.vat_codes.high' => '2',
    ]);

    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create([
        'name' => 'Kippenlever',
        'exact_article_code' => 'KL-001',
        'vat_category' => VatCategory::Low,
    ]));

    $product->defaultProductSupplier()?->update(['price_per_kg' => 4.56]);

    expect(ExactItemMapper::toExactItem($product->fresh()))->toBe([
        'Code' => 'KL-001',
        'Description' => 'Kippenlever',
        'Unit' => 'kg',
        'IsSalesItem' => true,
        'IsStockItem' => false,
        'IsPurchaseItem' => false,
        'SalesVatCode' => '1',
        'StandardSalesPrice' => 4.56,
    ]);
});

it('generates a fallback article code when none is configured', function () {
    config(['exact.item.code_prefix' => 'KOYLU']);

    $product = Product::factory()->make([
        'id' => 8,
        'exact_article_code' => null,
    ]);

    expect(ExactItemMapper::articleCode($product))->toBe('KOYLU-P-8');
});

it('parses product ids from generated article codes', function () {
    config(['exact.item.code_prefix' => 'KOYLU']);

    expect(ExactItemMapper::productIdFromArticleCode('KOYLU-P-15'))->toBe(15)
        ->and(ExactItemMapper::productIdFromArticleCode('KOYLU-15'))->toBeNull();
});

it('maps high vat products to the configured exact vat code', function () {
    config(['exact.item.vat_codes.high' => '21']);

    $product = Product::factory()->make([
        'vat_category' => VatCategory::High,
    ]);

    expect(ExactItemMapper::salesVatCode($product))->toBe('21');
});

it('omits optional exact vat codes when they are not configured', function () {
    config([
        'exact.item.vat_codes.low' => null,
        'exact.item.vat_codes.high' => null,
    ]);

    $product = Product::factory()->make([
        'name' => 'Test',
        'vat_category' => VatCategory::High,
    ]);

    expect(ExactItemMapper::toExactItem($product))
        ->not->toHaveKey('SalesVatCode');
});
