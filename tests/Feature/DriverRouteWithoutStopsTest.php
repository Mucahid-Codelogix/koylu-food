<?php

use App\Enums\RouteStatus;
use App\Filament\Driver\Pages\DriverDashboard;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\RouteWorkflowService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = User::factory()->driver()->create();

    $this->actingAs($this->driver);
    Filament::setCurrentPanel('driver');
});

it('toont op het dashboard geen laadknoppen bij een gestarte route zonder stops', function () {
    Route::factory()->inProgress()->create(['driver_id' => $this->driver->id]);

    Testable::create(DriverDashboard::class)
        ->assertSee('Geen stops op deze route')
        ->assertDontSee('Verder met laden')
        ->assertDontSee('Start laden')
        ->assertDontSee('Alle leverbonnen printen');
});

it('start een geplande route zonder stops niet', function () {
    $route = Route::factory()->create(['driver_id' => $this->driver->id]);

    Testable::create(DriverDashboard::class)
        ->assertDontSee('Start laden')
        ->call('startLoading')
        ->assertNotified('Deze route heeft geen stops. Neem contact op met de planner.')
        ->assertNoRedirect();

    expect($route->fresh()->status)->toBe(RouteStatus::PLANNED);
});

it('stuurt de chauffeur vanaf het laadscherm terug als de route geen stops heeft', function () {
    $route = Route::factory()->inProgress()->create(['driver_id' => $this->driver->id]);

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->assertNotified('Deze route heeft geen stops. Neem contact op met de planner.')
        ->assertRedirect(DriverDashboard::getUrl());
});

it('toont op het laadscherm een melding als er geen producten te laden zijn', function () {
    $route = Route::factory()->inProgress()->create(['driver_id' => $this->driver->id]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => Order::factory()->placed()->for(Customer::factory())->create()->id,
        'stop_order' => 1,
    ]);

    Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])
        ->assertSee('Niets te laden')
        ->assertSee('Terug naar Mijn Route');
});

it('weigert laden en starten van een route zonder stops in de workflow', function () {
    $route = Route::factory()->create(['driver_id' => $this->driver->id]);

    expect(fn () => app(RouteWorkflowService::class)->startRoute($route))
        ->toThrow(DomainException::class, 'Deze route heeft geen stops.')
        ->and(fn () => app(RouteWorkflowService::class)->assertCanAccessLoading($route, $this->driver))
        ->toThrow(DomainException::class, 'Deze route heeft geen stops.');
});
