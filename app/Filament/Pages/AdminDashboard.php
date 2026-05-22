<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Routes\RouteResource;
use App\Services\AdminDashboardService;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class AdminDashboard extends BaseDashboard
{
    protected static string $routePath = '/';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::Home;

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected string $view = 'filament.admin.pages.admin-dashboard';

    public int $newOrdersCount = 0;

    public int $totalStopsToday = 0;

    public int $deliveredStopsToday = 0;

    public $newOrders;

    public $driverRoutes;

    public function mount(): void
    {
        $data = app(AdminDashboardService::class)->getData();

        $this->newOrdersCount = $data['new_orders_count'];
        $this->newOrders = $data['new_orders'];
        $this->totalStopsToday = $data['total_stops_today'];
        $this->deliveredStopsToday = $data['delivered_stops_today'];
        $this->driverRoutes = $data['driver_routes'];
    }

    public static function ordersIndexUrl(): string
    {
        return OrderResource::getUrl('index');
    }

    public static function orderViewUrl(int $orderId): string
    {
        return OrderResource::getUrl('view', ['record' => $orderId]);
    }

    public static function routeViewUrl(int $routeId): string
    {
        return RouteResource::getUrl('view', ['record' => $routeId]);
    }

    public static function routesTodayUrl(): string
    {
        return RouteResource::getUrl('index');
    }
}
