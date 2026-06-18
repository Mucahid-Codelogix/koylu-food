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
use App\Services\RouteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns a placed order to a route and marks it routed', function () {
    $driver = User::factory()->create(['role' => UserRole::DRIVER]);
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'status' => RouteStatus::PLANNED,
    ]);
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    $stop = app(RouteWorkflowService::class)->assignOrderToRoute($route, $order);

    expect($stop->route_id)->toBe($route->id)
        ->and($stop->stop_order)->toBe(1)
        ->and($stop->status)->toBe(RouteStopStatus::PENDING)
        ->and($order->fresh()->status)->toBe(OrderStatus::ROUTED);
});

it('increments stop_order automatically for each new stop on a route', function () {
    $route = Route::factory()->create([
        'driver_id' => User::factory()->create(['role' => UserRole::DRIVER])->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
    ]);
    $firstOrder = Order::factory()->placed()->for(Customer::factory())->create();
    $secondOrder = Order::factory()->placed()->for(Customer::factory())->create();

    $workflow = app(RouteWorkflowService::class);

    $firstStop = $workflow->assignOrderToRoute($route, $firstOrder);
    $secondStop = $workflow->assignOrderToRoute($route, $secondOrder);

    expect($firstStop->stop_order)->toBe(1)
        ->and($secondStop->stop_order)->toBe(2);
});

it('scopes stop_order per route not across all routes', function () {
    $workflow = app(RouteWorkflowService::class);
    $vehicleId = Vehicle::factory()->create()->id;
    $driverId = User::factory()->create(['role' => UserRole::DRIVER])->id;

    $routeA = Route::factory()->create(['driver_id' => $driverId, 'vehicle_id' => $vehicleId]);
    $routeB = Route::factory()->create(['driver_id' => $driverId, 'vehicle_id' => $vehicleId]);

    foreach (range(1, 14) as $i) {
        $workflow->assignOrderToRoute(
            $routeB,
            Order::factory()->placed()->for(Customer::factory())->create(),
        );
    }

    $stopOnRouteA = $workflow->assignOrderToRoute(
        $routeA,
        Order::factory()->placed()->for(Customer::factory())->create(),
    );

    expect($stopOnRouteA->stop_order)->toBe(1)
        ->and($routeB->routeStops()->max('stop_order'))->toBe(14);
});

it('prevents assigning the same order twice', function () {
    $driver = User::factory()->create(['role' => UserRole::DRIVER]);
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
    ]);
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    app(RouteWorkflowService::class)->assignOrderToRoute($route, $order);

    app(RouteWorkflowService::class)->assignOrderToRoute($route, $order);
})->throws(DomainException::class);

it('completes a route when all stops are delivered or skipped', function () {
    $route = Route::factory()->inProgress()->create([
        'loading_completed_at' => now(),
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'status' => RouteStopStatus::DELIVERED,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'status' => RouteStopStatus::SKIPPED,
    ]);

    $completed = app(RouteWorkflowService::class)->completeRouteIfReady($route);

    expect($completed)->toBeTrue()
        ->and($route->fresh()->status)->toBe(RouteStatus::COMPLETED);
});

it('blocks delivery access when loading is not completed', function () {
    $driver = User::factory()->create(['role' => UserRole::DRIVER]);
    $route = Route::factory()->inProgress()->create([
        'driver_id' => $driver->id,
        'loading_completed_at' => null,
    ]);

    app(RouteWorkflowService::class)->assertCanAccessDelivery($route, $driver);
})->throws(DomainException::class);

it('reopens a skipped stop and reactivates a completed route', function () {
    $route = Route::factory()->create([
        'status' => RouteStatus::COMPLETED,
        'completed_at' => now(),
        'loading_completed_at' => now(),
    ]);

    $stop = RouteStop::factory()->create([
        'route_id' => $route->id,
        'status' => RouteStopStatus::SKIPPED,
    ]);

    $reopened = app(RouteWorkflowService::class)->reopenStop($route, $stop);

    expect($reopened->status)->toBe(RouteStopStatus::PENDING)
        ->and($route->fresh()->status)->toBe(RouteStatus::IN_PROGRESS)
        ->and($route->fresh()->completed_at)->toBeNull();
});
