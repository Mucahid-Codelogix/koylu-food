<?php

namespace App\Filament\Driver\Pages;

use App\Models\OrderItem;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Services\OrderItemLoadingService;
use App\Services\RouteWorkflowService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

class DriverLoadingPhase extends Page
{
    protected string $view = 'filament.driver.pages.driver-loading-phase';

    protected static ?string $title = 'Laden';

    protected static bool $shouldRegisterNavigation = false;

    public Route $route;

    public int $currentProductIndex = 0;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<int, array{loaded_gram_variant_id: int|null, substitution_reason: string}> */
    public array $loadingData = [];

    public function mount(): void
    {
        $routeId = request()->query('routeId') ?? request()->route('routeId');

        $this->route = Route::with([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product.activeGramVariants',
            'routeStops.order.items.productGramVariant',
            'routeStops.order.items.loadedGramVariant',
        ])->findOrFail($routeId);

        try {
            app(RouteWorkflowService::class)->assertCanAccessLoading($this->route, auth()->user());
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
            $this->redirect(DriverDashboard::getUrl());

            return;
        }

        $this->initLoadingData();
        $this->products = $this->groupByProduct();
    }

    private function initLoadingData(): void
    {
        foreach ($this->route->routeStops as $stop) {
            foreach ($stop->order->items as $item) {
                if (! $item->isWholeChicken()) {
                    continue;
                }

                $this->loadingData[$item->id] = [
                    'loaded_gram_variant_id' => $item->loaded_gram_variant_id
                        ?? $item->product_gram_variant_id,
                    'substitution_reason' => $item->loading_substitution_reason ?? '',
                ];
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
                        'is_whole_chicken' => $item->isWholeChicken(),
                        'allows_substitute' => (bool) $item->product?->allows_loading_substitute,
                        'total' => 0,
                        'total_kg' => 0,
                        'total_pieces' => 0,
                        'customers' => [],
                    ];
                }

                $boxQty = (float) $item->quantity;
                $products[$productId]['customers'][] = [
                    'order_item_id' => $item->id,
                    'stop_order' => $stop->stop_order,
                    'customer_name' => $stop->order->customer->company_name,
                    'city' => $stop->order->customer->city,
                    'quantity' => $boxQty,
                    'unit' => $item->unit,
                    'is_whole_chicken' => $item->isWholeChicken(),
                    'ordered_variant_label' => $item->productGramVariant?->displayLabel() ?? '—',
                    'ordered_pieces' => $item->ordered_pieces,
                    'ordered_total_weight_kg' => $item->ordered_total_weight_kg,
                    'allows_substitute' => (bool) $item->product?->allows_loading_substitute,
                    'gram_variants' => $item->product?->activeGramVariants ?? collect(),
                ];

                $products[$productId]['total'] += $boxQty;

                if ($item->isWholeChicken()) {
                    $products[$productId]['total_kg'] += (float) $item->ordered_total_weight_kg;
                    $products[$productId]['total_pieces'] += (float) $item->ordered_pieces;
                }
            }
        }

        foreach ($products as &$product) {
            usort($product['customers'], fn ($a, $b) => $a['stop_order'] <=> $b['stop_order']);
        }

        return array_values($products);
    }

    public function saveLoadedVariant(int $orderItemId): void
    {
        $orderItem = OrderItem::query()
            ->with(['product.activeGramVariants', 'productGramVariant'])
            ->findOrFail($orderItemId);

        if (! $orderItem->isWholeChicken()) {
            return;
        }

        $data = $this->loadingData[$orderItemId] ?? [];
        $variantId = $data['loaded_gram_variant_id'] ?? $orderItem->product_gram_variant_id;

        $variant = $orderItem->product?->activeGramVariants
            ->firstWhere('id', $variantId)
            ?? ProductGramVariant::query()->find($variantId);

        if (! $variant) {
            Notification::make()->title('Ongeldige gramvariant')->danger()->send();

            return;
        }

        $reason = filled($data['substitution_reason'] ?? null)
            ? $data['substitution_reason']
            : null;

        if ($variant->id !== $orderItem->product_gram_variant_id && ! $orderItem->product?->allows_loading_substitute) {
            Notification::make()
                ->title('Alternatief niet toegestaan voor dit product')
                ->warning()
                ->send();

            $this->loadingData[$orderItemId]['loaded_gram_variant_id'] = $orderItem->product_gram_variant_id;

            return;
        }

        if ($variant->id !== $orderItem->product_gram_variant_id && blank($reason)) {
            Notification::make()
                ->title('Geef een reden op bij een alternatieve variant')
                ->warning()
                ->send();

            return;
        }

        app(OrderItemLoadingService::class)->recordLoadedVariant($orderItem, $variant, $reason);

        Notification::make()->title('Geladen variant opgeslagen')->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
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
        $loadingService = app(OrderItemLoadingService::class);

        foreach ($this->route->routeStops as $stop) {
            foreach ($stop->order->items as $item) {
                if ($item->isWholeChicken() && $item->loaded_at === null) {
                    $loadingService->initializeFromOrder($item->fresh(['productGramVariant']));
                }
            }
        }

        app(RouteWorkflowService::class)->completeLoading($this->route->fresh());

        $this->redirect(
            DriverDeliveryPhase::getUrl().'?routeId='.$this->route->id
        );
    }
}
