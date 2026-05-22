<?php

namespace App\Services;

use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\Order;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class RouteWorkflowService
{
    public function __construct(
        protected OrderWorkflowService $orders,
    ) {}

    public function assignOrderToRoute(Route $route, Order $order, ?int $stopOrder = null): RouteStop
    {
        $this->orders->assertCanBeRouted($order);

        $stopOrder ??= (int) $route->routeStops()->max('stop_order') + 1;

        return RouteStop::create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
            'status' => RouteStopStatus::PENDING,
        ]);
    }

    public function skipStop(RouteStop $stop): RouteStop
    {
        if ($stop->status !== RouteStopStatus::PENDING) {
            throw new DomainException('Alleen openstaande stops kunnen worden overgeslagen.');
        }

        $stop->update(['status' => RouteStopStatus::SKIPPED]);

        return $stop->fresh();
    }

    public function markStopDelivered(RouteStop $stop): RouteStop
    {
        $stop->update(['status' => RouteStopStatus::DELIVERED]);

        return $stop->fresh();
    }

    public function allStopsResolved(Route $route): bool
    {
        return $route->routeStops()
            ->whereNotIn('status', [
                RouteStopStatus::DELIVERED->value,
                RouteStopStatus::SKIPPED->value,
            ])
            ->doesntExist();
    }

    public function completeRouteIfReady(Route $route): bool
    {
        $route->refresh();

        if (! $this->allStopsResolved($route)) {
            return false;
        }

        $route->update([
            'status' => RouteStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        return true;
    }

    public function firstPendingStopIndex(Route $route): int|false
    {
        $route->loadMissing(['routeStops' => fn ($q) => $q->orderBy('stop_order')]);

        return $route->routeStops
            ->search(fn (RouteStop $stop) => $stop->status === RouteStopStatus::PENDING);
    }

    public function assertDriverOwnsRoute(Route $route, User $driver): void
    {
        if ($route->driver_id !== $driver->id) {
            throw new AuthorizationException('Geen toegang tot deze route.');
        }
    }

    public function assertCanAccessLoading(Route $route, User $driver): void
    {
        $this->assertDriverOwnsRoute($route, $driver);

        if (! in_array($route->status, [RouteStatus::PLANNED, RouteStatus::IN_PROGRESS], true)) {
            throw new DomainException('Deze route kan niet meer geladen worden.');
        }
    }

    public function assertCanAccessDelivery(Route $route, User $driver): void
    {
        $this->assertDriverOwnsRoute($route, $driver);

        if ($route->status !== RouteStatus::IN_PROGRESS) {
            throw new DomainException('Leveren kan alleen tijdens een actieve route.');
        }

        if ($route->loading_completed_at === null) {
            throw new DomainException('Rond eerst het laden af voordat je gaat leveren.');
        }
    }

    public function startRoute(Route $route): Route
    {
        if ($route->status !== RouteStatus::PLANNED) {
            return $route;
        }

        $route->update([
            'status' => RouteStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);

        return $route->fresh();
    }

    public function completeLoading(Route $route): Route
    {
        if ($route->loading_completed_at !== null) {
            return $route;
        }

        if ($route->status === RouteStatus::PLANNED) {
            $this->startRoute($route);
        }

        $route->update(['loading_completed_at' => now()]);

        return $route->fresh();
    }
}
