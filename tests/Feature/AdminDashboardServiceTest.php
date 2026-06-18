<?php

use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts new placed orders', function () {
    $customer = Customer::factory()->create();

    Order::create([
        'order_number' => 'ORD-TEST01',
        'customer_id' => $customer->id,
        'status' => OrderStatus::PLACED,
        'order_date' => today(),
        'total_price' => 100,
    ]);

    Order::create([
        'order_number' => 'ORD-TEST02',
        'customer_id' => $customer->id,
        'status' => OrderStatus::ROUTED,
        'order_date' => today(),
        'total_price' => 50,
    ]);

    $data = app(AdminDashboardService::class)->getData();

    expect($data['new_orders_count'])->toBe(1)
        ->and($data['new_orders_preview'])->toHaveCount(1)
        ->and($data['new_orders_overflow'])->toBe(0);
});

it('limits order previews and reports overflow', function () {
    $customer = Customer::factory()->create();

    foreach (range(1, 7) as $index) {
        Order::create([
            'order_number' => 'ORD-OVER'.$index,
            'customer_id' => $customer->id,
            'status' => OrderStatus::PLACED,
            'order_date' => today(),
            'total_price' => 10 * $index,
        ]);
    }

    $data = app(AdminDashboardService::class)->getData();

    expect($data['new_orders_count'])->toBe(7)
        ->and($data['new_orders_preview'])->toHaveCount(AdminDashboardService::ORDERS_PREVIEW_LIMIT)
        ->and($data['new_orders_overflow'])->toBe(2);
});

it('aggregates today routes per driver with delivery progress', function () {
    $customer = Customer::factory()->create();
    $driver = User::factory()->create(['role' => UserRole::DRIVER, 'name' => 'Jan Chauffeur']);
    $vehicle = Vehicle::factory()->create();

    $deliveredOrder = Order::create([
        'order_number' => 'ORD-ROUTE1',
        'customer_id' => $customer->id,
        'status' => OrderStatus::PLACED,
        'order_date' => today(),
        'total_price' => 80,
    ]);

    $pendingOrder = Order::create([
        'order_number' => 'ORD-ROUTE2',
        'customer_id' => $customer->id,
        'status' => OrderStatus::PLACED,
        'order_date' => today(),
        'total_price' => 60,
    ]);

    $route = Route::create([
        'driver_id' => $driver->id,
        'vehicle_id' => $vehicle->id,
        'route_date' => today(),
        'status' => RouteStatus::IN_PROGRESS,
    ]);

    $deliveredStop = RouteStop::create([
        'route_id' => $route->id,
        'order_id' => $deliveredOrder->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    RouteStop::create([
        'route_id' => $route->id,
        'order_id' => $pendingOrder->id,
        'stop_order' => 2,
        'status' => RouteStopStatus::PENDING,
    ]);

    $deliveredStop->update(['status' => RouteStopStatus::DELIVERED]);

    $data = app(AdminDashboardService::class)->getData();

    expect($data['total_stops_today'])->toBe(2)
        ->and($data['delivered_stops_today'])->toBe(1)
        ->and($data['pending_stops_today'])->toBe(1)
        ->and($data['driver_routes_preview'])->toHaveCount(1)
        ->and($data['driver_routes_preview']->first()['driver_name'])->toBe('Jan Chauffeur')
        ->and($data['driver_routes_preview']->first()['progress_percent'])->toBe(50)
        ->and($data['driver_routes_preview']->first()['next_pending_stop'])->not->toBeNull();
});
