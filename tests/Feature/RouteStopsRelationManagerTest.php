<?php

use App\Enums\RouteStatus;
use App\Filament\Resources\Routes\Pages\ViewRoute;
use App\Filament\Resources\Routes\RelationManagers\RouteStopsRelationManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function routeForStops(RouteStatus $status = RouteStatus::PLANNED): Route
{
    return Route::factory()->create([
        'driver_id' => User::factory()->driver()->create()->id,
        'vehicle_id' => Vehicle::factory()->create()->id,
        'status' => $status,
    ]);
}

function stopOnRoute(Route $route): RouteStop
{
    return RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => Order::factory()->placed()->for(Customer::factory())->create()->id,
    ]);
}

function routeStopsManager(Route $route): Testable
{
    return Livewire::test(RouteStopsRelationManager::class, [
        'ownerRecord' => $route,
        'pageClass' => ViewRoute::class,
    ]);
}

it('laat stops direct toevoegen vanaf de view-pagina van een route', function () {
    $this->actingAs(User::factory()->admin()->create());

    $route = routeForStops();
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    routeStopsManager($route)
        ->assertSuccessful()
        ->callAction(TestAction::make('create')->table(), data: ['order_id' => $order->id]);

    expect($route->routeStops()->where('order_id', $order->id)->exists())->toBeTrue();
});

it('laat stops direct verwijderen vanaf de view-pagina van een route', function () {
    $this->actingAs(User::factory()->admin()->create());

    $route = routeForStops();
    $stop = stopOnRoute($route);

    routeStopsManager($route)
        ->assertActionVisible(TestAction::make('edit')->table($stop))
        ->callAction(TestAction::make('delete')->table($stop));

    expect(RouteStop::query()->whereKey($stop->id)->exists())->toBeFalse();
});

it('blokkeert wijzigen van stops op een afgeronde route', function () {
    $this->actingAs(User::factory()->admin()->create());

    $route = routeForStops(RouteStatus::COMPLETED);
    $stop = stopOnRoute($route);

    routeStopsManager($route)
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('edit')->table($stop))
        ->assertActionHidden(TestAction::make('delete')->table($stop));
});
