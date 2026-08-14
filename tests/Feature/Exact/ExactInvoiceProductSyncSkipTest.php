<?php

use App\Models\Product;
use App\Services\Exact\ExactInvoiceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function isSyncedWithExact(Product $product): bool
{
    $method = new ReflectionMethod(ExactInvoiceSyncService::class, 'isSyncedWithExact');

    return $method->invoke(app(ExactInvoiceSyncService::class), $product);
}

it('slaat het opnieuw synchroniseren over voor een ongewijzigd product', function () {
    $product = Product::factory()->create();

    $product->forceFill([
        'exact_item_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'exact_synced_at' => now(),
    ])->saveQuietly();

    expect(isSyncedWithExact($product->fresh()))->toBeTrue();
});

it('synchroniseert opnieuw wanneer het product na de laatste sync is gewijzigd', function () {
    $product = Product::factory()->create();

    $product->forceFill([
        'exact_item_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        'exact_synced_at' => now()->subHour(),
    ])->saveQuietly();

    $product->update(['name' => 'Nieuwe naam']);

    expect(isSyncedWithExact($product->fresh()))->toBeFalse();
});

it('synchroniseert wanneer het exact-artikel-id nog ontbreekt', function () {
    $product = Product::factory()->create();

    $product->forceFill([
        'exact_item_id' => null,
        'exact_synced_at' => now(),
    ])->saveQuietly();

    expect(isSyncedWithExact($product->fresh()))->toBeFalse();
});
