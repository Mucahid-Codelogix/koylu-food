<?php

namespace App\Filament\Driver\Pages;

use App\Enums\RouteStatus;
use App\Models\Route;
use App\Services\RouteWorkflowService;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class DriverDashboard extends Page
{
    protected string $view = 'filament.driver.pages.driver-dashboard';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-map';

    protected static ?string $title = 'Mijn Route';

    protected static ?string $slug = 'dashboard';

    public ?Route $route = null;

    public Collection $pastRoutes;

    public Collection $futureRoutes;

    public function mount(): void
    {
        $this->route = Route::with([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
            'vehicle',
        ])
            ->where('driver_id', auth()->id())
            ->whereDate('route_date', today())
            ->whereIn('status', [RouteStatus::PLANNED, RouteStatus::IN_PROGRESS])
            ->first();

        $this->pastRoutes = Route::with(['routeStops', 'vehicle'])
            ->where('driver_id', auth()->id())
            ->where('status', RouteStatus::COMPLETED)
            ->orderByDesc('route_date')
            ->limit(5)
            ->get();

        $this->futureRoutes = Route::with(['routeStops', 'vehicle'])
            ->where('driver_id', auth()->id())
            ->whereDate('route_date', '>', today())
            ->where('status', RouteStatus::PLANNED)
            ->orderBy('route_date')
            ->limit(5)
            ->get();
    }

    public function startLoading(): void
    {
        if (! $this->route) {
            return;
        }

        app(RouteWorkflowService::class)->startRoute($this->route);

        $this->redirect(
            DriverLoadingPhase::getUrl().'?routeId='.$this->route->id
        );
    }

    public function continueLoading(): void
    {
        if (! $this->route) {
            return;
        }

        $this->redirect(
            DriverLoadingPhase::getUrl().'?routeId='.$this->route->id
        );
    }

    public function continueDelivery(): void
    {
        if (! $this->route) {
            return;
        }

        $this->redirect(
            DriverDeliveryPhase::getUrl().'?routeId='.$this->route->id
        );
    }
}
