<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Enums\VatCategory;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the database with demo data for each process stage', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@koylu.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'driver@koylu.test')->exists())->toBeTrue()
        ->and(Product::query()->where('product_type', ProductType::WholeChicken)->exists())->toBeTrue()
        ->and(Order::query()->where('status', OrderStatus::PLACED)->count())->toBeGreaterThan(0)
        ->and(OrderItem::query()->whereNotNull('product_gram_variant_id')->exists())->toBeTrue()
        ->and(Route::query()->whereDate('route_date', today())->where('status', RouteStatus::PLANNED)->exists())->toBeTrue()
        ->and(Route::query()->whereDate('route_date', today())->where('status', RouteStatus::IN_PROGRESS)->exists())->toBeTrue()
        ->and(Invoice::query()->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-PARTIAL'))->where('status', InvoiceStatus::CONCEPT)->exists())->toBeTrue()
        ->and((float) Invoice::query()->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-PARTIAL'))->value('total_amount'))->toBe(78.53)
        ->and((float) Invoice::query()->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-MIXED'))->value('total_amount'))->toBe(169.50)
        ->and((float) Invoice::query()->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-VRIJ'))->value('vat_amount'))->toBe(0.0)
        ->and((float) Invoice::query()->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-VRIJ'))->value('total_amount'))->toBe(64.90);

    $mixed = Invoice::query()
        ->whereHas('order', fn ($q) => $q->where('order_number', 'DEMO-INV-MIXED'))
        ->firstOrFail();

    expect($mixed->vatByRate())->toHaveCount(2)
        ->and($mixed->formattedVatBreakdown())->toContain('BTW (9%)')
        ->and($mixed->formattedVatBreakdown())->toContain('BTW (21%)');

    expect(Product::query()->where('name', 'Kippenlever')->value('vat_category'))->toBe(VatCategory::Low);
});
