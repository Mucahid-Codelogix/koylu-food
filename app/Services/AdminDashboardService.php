<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Models\ExactToken;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    public const ORDERS_PREVIEW_LIMIT = 5;

    public const INVOICES_PREVIEW_LIMIT = 3;

    public const ROUTES_PREVIEW_LIMIT = 4;

    /**
     * @return array{
     *     new_orders_count: int,
     *     new_orders_preview: Collection<int, Order>,
     *     new_orders_overflow: int,
     *     concept_invoices_count: int,
     *     concept_invoices_preview: Collection<int, Invoice>,
     *     concept_invoices_overflow: int,
     *     total_stops_today: int,
     *     delivered_stops_today: int,
     *     pending_stops_today: int,
     *     active_routes_count: int,
     *     driver_routes_preview: Collection<int, array{
     *         route: Route,
     *         driver_name: string,
     *         stops_total: int,
     *         stops_delivered: int,
     *         stops_pending: int,
     *         progress_percent: int,
     *         next_pending_stop: ?RouteStop,
     *         remaining_stops: int,
     *     }>,
     *     driver_routes_overflow: int,
     *     exact: array{
     *         is_connected: bool,
     *         expires_at: ?string,
     *         division: ?int,
     *         sync_errors_count: int,
     *         failed_logs_count: int,
     *     }
     * }
     */
    public function getData(): array
    {
        $newOrdersCount = Order::query()->where('status', OrderStatus::PLACED)->count();

        $newOrdersPreview = Order::query()
            ->with('customer')
            ->where('status', OrderStatus::PLACED)
            ->orderByDesc('created_at')
            ->limit(self::ORDERS_PREVIEW_LIMIT)
            ->get();

        $conceptInvoicesCount = Invoice::query()
            ->where('status', InvoiceStatus::CONCEPT)
            ->count();

        $conceptInvoicesPreview = Invoice::query()
            ->with('order.customer')
            ->where('status', InvoiceStatus::CONCEPT)
            ->orderByDesc('created_at')
            ->limit(self::INVOICES_PREVIEW_LIMIT)
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
            ->sortBy(fn (Route $route): int => $this->routeSortPriority($route));

        $driverRoutes = $routesToday->map(fn (Route $route): array => $this->mapDriverRoute($route))->values();

        $driverRoutesPreview = $driverRoutes->take(self::ROUTES_PREVIEW_LIMIT)->values();

        return [
            'new_orders_count' => $newOrdersCount,
            'new_orders_preview' => $newOrdersPreview,
            'new_orders_overflow' => max(0, $newOrdersCount - self::ORDERS_PREVIEW_LIMIT),
            'concept_invoices_count' => $conceptInvoicesCount,
            'concept_invoices_preview' => $conceptInvoicesPreview,
            'concept_invoices_overflow' => max(0, $conceptInvoicesCount - self::INVOICES_PREVIEW_LIMIT),
            'total_stops_today' => $driverRoutes->sum('stops_total'),
            'delivered_stops_today' => $driverRoutes->sum('stops_delivered'),
            'pending_stops_today' => $driverRoutes->sum('stops_pending'),
            'active_routes_count' => $driverRoutes->filter(
                fn (array $item): bool => $item['route']->status === RouteStatus::IN_PROGRESS
            )->count(),
            'driver_routes_preview' => $driverRoutesPreview,
            'driver_routes_overflow' => max(0, $driverRoutes->count() - self::ROUTES_PREVIEW_LIMIT),
            'exact' => $this->exactStatus(),
        ];
    }

    /**
     * @return array{
     *     route: Route,
     *     driver_name: string,
     *     stops_total: int,
     *     stops_delivered: int,
     *     stops_pending: int,
     *     progress_percent: int,
     *     next_pending_stop: ?RouteStop,
     *     remaining_stops: int,
     * }
     */
    private function mapDriverRoute(Route $route): array
    {
        $stops = $route->routeStops;
        $total = $stops->count();
        $delivered = $stops->filter(fn ($stop): bool => $this->stopIsDelivered($stop->status))->count();
        $pending = $stops->filter(fn ($stop): bool => $this->stopIsPending($stop->status))->count();

        $nextPendingStop = $stops->first(
            fn (RouteStop $stop): bool => $this->stopIsPending($stop->status)
        );

        return [
            'route' => $route,
            'driver_name' => $route->driver?->name ?? 'Onbekende chauffeur',
            'stops_total' => $total,
            'stops_delivered' => $delivered,
            'stops_pending' => $pending,
            'progress_percent' => $total > 0 ? (int) round(($delivered / $total) * 100) : 0,
            'next_pending_stop' => $nextPendingStop,
            'remaining_stops' => max(0, $pending - ($nextPendingStop ? 1 : 0)),
        ];
    }

    private function routeSortPriority(Route $route): int
    {
        return match ($route->status) {
            RouteStatus::IN_PROGRESS => 0,
            RouteStatus::PLANNED => 1,
            RouteStatus::COMPLETED => 2,
            default => 3,
        };
    }

    /**
     * @return array{
     *     is_connected: bool,
     *     expires_at: ?string,
     *     division: ?int,
     *     sync_errors_count: int,
     *     failed_logs_count: int,
     * }
     */
    private function exactStatus(): array
    {
        $token = ExactToken::stored();

        return [
            'is_connected' => app(ExactOnlineClient::class)->isConnected(),
            'expires_at' => $token?->expires_at?->timezone(config('app.timezone'))->format('d-m-Y H:i'),
            'division' => $token?->division,
            'sync_errors_count' => Customer::query()->whereNotNull('exact_sync_error')->count()
                + Product::query()->whereNotNull('exact_sync_error')->count()
                + Invoice::query()->whereNotNull('exact_sync_error')->count(),
            'failed_logs_count' => ExactSyncLog::query()
                ->where('status', ExactSyncLogger::STATUS_FAILED)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
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
