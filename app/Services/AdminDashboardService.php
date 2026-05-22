<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RouteStopStatus;
use App\Models\Order;
use App\Models\Route;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    /**
     * @return array{
     *     new_orders_count: int,
     *     new_orders: Collection<int, Order>,
     *     total_stops_today: int,
     *     delivered_stops_today: int,
     *     driver_routes: Collection<int, array{
     *         route: Route,
     *         driver_name: string,
     *         stops_total: int,
     *         stops_delivered: int,
     *         stops_pending: int,
     *         progress_percent: int,
     *     }>
     * }
     */
    public function getData(): array
    {
        $newOrders = Order::query()
            ->with('customer')
            ->where('status', OrderStatus::PLACED)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $routesToday = Route::query()
            ->with([
                'driver',
                'vehicle',
                'routeStops' => fn ($query) => $query->orderBy('stop_order'),
                'routeStops.order.customer',
            ])
            ->whereDate('route_date', today())
            ->get()
            ->sortBy(fn (Route $route): string => $route->driver?->name ?? '');

        $driverRoutes = $routesToday->map(function (Route $route): array {
            $stops = $route->routeStops;
            $total = $stops->count();
            $delivered = $stops->filter(
                fn ($stop): bool => $this->stopIsDelivered($stop->status)
            )->count();
            $pending = $stops->filter(
                fn ($stop): bool => $this->stopIsPending($stop->status)
            )->count();

            return [
                'route' => $route,
                'driver_name' => $route->driver?->name ?? 'Onbekende chauffeur',
                'stops_total' => $total,
                'stops_delivered' => $delivered,
                'stops_pending' => $pending,
                'progress_percent' => $total > 0 ? (int) round(($delivered / $total) * 100) : 0,
            ];
        })->values();

        return [
            'new_orders_count' => Order::query()->where('status', OrderStatus::PLACED)->count(),
            'new_orders' => $newOrders,
            'total_stops_today' => $driverRoutes->sum('stops_total'),
            'delivered_stops_today' => $driverRoutes->sum('stops_delivered'),
            'driver_routes' => $driverRoutes,
        ];
    }

    private function stopIsDelivered(mixed $status): bool
    {
        return $status === RouteStopStatus::DELIVERED
            || $status === RouteStopStatus::DELIVERED->value;
    }

    private function stopIsPending(mixed $status): bool
    {
        return $status === RouteStopStatus::PENDING
            || $status === RouteStopStatus::PENDING->value;
    }
}
