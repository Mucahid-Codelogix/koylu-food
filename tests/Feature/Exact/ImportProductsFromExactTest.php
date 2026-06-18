<?php

use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Jobs\ImportProductsFromExact;
use App\Jobs\SyncProductToExact;
use App\Models\Product;
use App\Models\User;
use App\Services\Exact\ExactCustomerImportResult;
use App\Services\Exact\ExactItemMapper;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('imports products from exact via the job', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->mock(ExactProductImportService::class, function ($mock): void {
        $mock->shouldReceive('import')
            ->once()
            ->andReturn(new ExactCustomerImportResult(created: 8, updated: 2, skipped: 1));
    });

    (new ImportProductsFromExact($admin->id))->handle(
        app(ExactProductImportService::class),
        app(ExactOnlineClient::class),
    );

    expect($admin->fresh()->notifications)->toHaveCount(1);
});

it('imports products without dispatching a push back to exact', function () {
    Queue::fake();

    Product::withoutEvents(function (): void {
        Product::query()->create([
            'name' => 'Imported artikel',
            'product_type' => ProductType::Standard,
            'min_order_quantity' => 1,
            'exact_article_code' => 'IMP-001',
            'exact_synced_at' => now(),
            'vat_category' => VatCategory::High,
            'is_active' => true,
        ]);
    });

    Queue::assertNotPushed(SyncProductToExact::class);
});

it('does not dispatch sync when a product is created with exact sync metadata', function () {
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

it('parses product ids from generated exact article codes', function () {
    config(['exact.item.code_prefix' => 'KOYLU']);

    expect(ExactItemMapper::productIdFromArticleCode('KOYLU-P-22'))->toBe(22)
        ->and(ExactItemMapper::productIdFromArticleCode('OTHER-P-22'))->toBeNull();
});

it('dispatches the product import job from the products list action', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();

    $this->mock(ExactOnlineClient::class, function ($mock): void {
        $mock->shouldReceive('isConnected')->once()->andReturn(true);
    });

    $this->actingAs($admin);

    Livewire::test(ListProducts::class)
        ->callAction('importFromExact')
        ->assertNotified();

    Queue::assertPushed(ImportProductsFromExact::class, fn (ImportProductsFromExact $job): bool => $job->initiatedByUserId === $admin->id);
});
