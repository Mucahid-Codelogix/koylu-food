<?php

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RouteStatus;
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
        ->and(Route::query()->whereDate('route_date', today())->where('status', RouteStatus::IN_PROGRESS)->exists())->toBeTrue();
});
