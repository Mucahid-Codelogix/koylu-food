<?php

namespace App\Filament\Driver\Pages;

use App\Enums\RouteStatus;
use App\Models\Route;
use Filament\Pages\Page;

class DriverLoadingPhase extends Page
{
    protected string $view = 'filament.driver.pages.driver-loading-phase';

    protected static ?string $title = 'Laden';

    protected static bool $shouldRegisterNavigation = false;

    public Route $route;

    public int $currentProductIndex = 0;

    public array $products = [];

    public function mount(): void
    {
        $routeId = request()->query('routeId') ?? request()->route('routeId');

        $this->route = Route::with([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
        ])->findOrFail($routeId);

        abort_unless($this->route->driver_id === auth()->id(), 403);

        $this->products = $this->groupByProduct();
    }

    private function groupByProduct(): array
    {
        $products = [];

        foreach ($this->route->routeStops->sortBy('stop_order') as $stop) {
            foreach ($stop->order->items as $item) {
                $productId = $item->product_id;

                if (! isset($products[$productId])) {
                    $products[$productId] = [
                        'id' => $productId,
                        'name' => $item->product_name,
                        'unit' => $item->unit,
                        'total' => 0,
                        'customers' => [],
                    ];
                }

                $products[$productId]['customers'][] = [
                    'stop_order' => $stop->stop_order,
                    'customer_name' => $stop->order->customer->company_name,
                    'city' => $stop->order->customer->city,
                    'quantity' => $item->quantity,
                ];

                $products[$productId]['total'] += $item->quantity;
            }
        }

        // Sorteer customers binnen elk product op stop_order
        foreach ($products as &$product) {
            usort($product['customers'], fn ($a, $b) => $a['stop_order'] <=> $b['stop_order']);
        }

        return array_values($products);
    }

    public function getCurrentProduct(): array
    {
        return $this->products[$this->currentProductIndex] ?? [];
    }

    public function getTotalProducts(): int
    {
        return count($this->products);
    }

    public function nextProduct(): void
    {
        if ($this->currentProductIndex < count($this->products) - 1) {
            $this->currentProductIndex++;
        }
    }

    public function previousProduct(): void
    {
        if ($this->currentProductIndex > 0) {
            $this->currentProductIndex--;
        }
    }

    public function finishLoading(): void
    {
        $this->route->update([
            'loading_completed_at' => now(),
        ]);

        $this->redirect(
            DriverDeliveryPhase::getUrl().'?routeId='.$this->route->id
        );
    }
}
