<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExactSyncLogs\ExactSyncLogResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\QueueFailedJobs\QueueFailedJobResource;
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

    public int $newOrdersOverflow = 0;

    public int $conceptInvoicesCount = 0;

    public int $conceptInvoicesOverflow = 0;

    public int $totalStopsToday = 0;

    public int $deliveredStopsToday = 0;

    public int $pendingStopsToday = 0;

    public int $activeRoutesCount = 0;

    public int $driverRoutesOverflow = 0;

    public $newOrdersPreview;

    public $conceptInvoicesPreview;

    public $driverRoutesPreview;

    /** @var array<string, mixed> */
    public array $exact = [];

    public function mount(): void
    {
        $data = app(AdminDashboardService::class)->getData();

        $this->newOrdersCount = $data['new_orders_count'];
        $this->newOrdersPreview = $data['new_orders_preview'];
        $this->newOrdersOverflow = $data['new_orders_overflow'];
        $this->conceptInvoicesCount = $data['concept_invoices_count'];
        $this->conceptInvoicesPreview = $data['concept_invoices_preview'];
        $this->conceptInvoicesOverflow = $data['concept_invoices_overflow'];
        $this->totalStopsToday = $data['total_stops_today'];
        $this->deliveredStopsToday = $data['delivered_stops_today'];
        $this->pendingStopsToday = $data['pending_stops_today'];
        $this->activeRoutesCount = $data['active_routes_count'];
        $this->driverRoutesPreview = $data['driver_routes_preview'];
        $this->driverRoutesOverflow = $data['driver_routes_overflow'];
        $this->exact = $data['exact'];
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

    public static function exactConnectionUrl(): string
    {
        return ExactConnection::getUrl();
    }

    public static function exactSyncLogsUrl(): string
    {
        return ExactSyncLogResource::getUrl('index');
    }

    public static function failedQueueJobsUrl(): string
    {
        return QueueFailedJobResource::getUrl('index');
    }

    public static function conceptInvoicesUrl(): string
    {
        return InvoiceResource::getUrl('index');
    }

    public static function invoiceViewUrl(int $invoiceId): string
    {
        return InvoiceResource::getUrl('view', ['record' => $invoiceId]);
    }
}
