<?php

use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Jobs\SyncProductToExact;
use App\Models\Product;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactItemSyncService;
use App\Services\Exact\ExactOnlineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('stores sync metadata after a successful product sync', function () {
    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create([
        'name' => 'Kippendijen',
        'exact_article_code' => 'KD-100',
    ]));

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactItemSyncService::class, function ($mock) use ($product): void {
        $mock->shouldReceive('sync')
            ->once()
            ->withArgs(fn (Product $subject): bool => $subject->is($product))
            ->andReturn('item-guid');
    });

    (new SyncProductToExact($product))->handle(
        app(ExactItemSyncService::class),
        app(ExactOnlineClient::class),
    );

    $product->refresh();

    expect($product->exact_article_code)->toBe('KD-100')
        ->and($product->exact_synced_at)->not->toBeNull()
        ->and($product->exact_sync_error)->toBeNull();
});

it('records an error when exact is not connected for product sync', function () {
    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create());

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(false);
    });

    $this->mock(ExactItemSyncService::class, function ($mock): void {
        $mock->shouldNotReceive('sync');
    });

    (new SyncProductToExact($product))->handle(
        app(ExactItemSyncService::class),
        app(ExactOnlineClient::class),
    );

    $product->refresh();

    expect($product->exact_sync_error)->toBe('Exact Online is niet gekoppeld.');
});

it('records product sync failures', function () {
    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create());

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactItemSyncService::class, function ($mock): void {
        $mock->shouldReceive('sync')
            ->once()
            ->andThrow(new ExactApiException('Artikel sync mislukt'));
    });

    expect(fn () => (new SyncProductToExact($product))->handle(
        app(ExactItemSyncService::class),
        app(ExactOnlineClient::class),
    ))->toThrow(ExactApiException::class);

    $product->refresh();

    expect($product->exact_sync_error)->toBe('Artikel sync mislukt');
});

it('dispatches a sync job when a product is created', function () {
    Queue::fake();

    Product::factory()->standard()->create();

    Queue::assertPushed(SyncProductToExact::class);
});

it('dispatches a sync job when product data changes', function () {
    Queue::fake();

    $product = Product::factory()->standard()->create();

    Queue::assertPushed(SyncProductToExact::class, 1);

    $product->update(['name' => 'Nieuwe productnaam']);

    Queue::assertPushed(SyncProductToExact::class, 2);
});

it('does not dispatch a sync job when only exact sync metadata changes', function () {
    Queue::fake();

    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create());

    Queue::assertNotPushed(SyncProductToExact::class, 1);

    $product->update([
        'exact_article_code' => 'KD-200',
        'exact_synced_at' => now(),
        'exact_sync_error' => null,
    ]);

    Queue::assertNotPushed(SyncProductToExact::class);
});

it('does not dispatch sync when a product is imported with exact sync metadata', function () {
    Queue::fake();

    Product::query()->create([
        'name' => 'Imported',
        'product_type' => ProductType::Standard,
        'min_order_quantity' => 1,
        'exact_article_code' => 'IMP-002',
        'exact_synced_at' => now(),
        'vat_category' => VatCategory::High,
        'is_active' => true,
    ]);

    Queue::assertNotPushed(SyncProductToExact::class);
});
