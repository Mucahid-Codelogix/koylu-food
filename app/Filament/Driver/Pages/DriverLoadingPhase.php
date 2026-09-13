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

    /** @var array<int, array{loaded_gram_variant_id: int|null, substitution_reason: string, actual_weight_kg: string}> */
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
                $this->loadingData[$item->id] = [
                    'loaded_gram_variant_id' => $item->loaded_gram_variant_id
                        ?? $item->product_gram_variant_id,
                    'substitution_reason' => $item->loading_substitution_reason ?? '',
                    'actual_weight_kg' => $item->loaded_actual_weight_kg !== null
                        ? (string) $item->loaded_actual_weight_kg
                        : '',
                    'loaded_quantity' => (string) (float) ($item->loaded_packaging_quantity ?? $item->quantity),
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
                        'supplier_breakdown' => [],
                        'variant_breakdown' => [],
                        'packaging_breakdown' => [],
                        'customers' => [],
                    ];
                }

                $orderedQty = (float) $item->quantity;
                $boxQty = (float) ($item->loaded_packaging_quantity ?? $item->quantity);
                $products[$productId]['customers'][] = [
                    'order_item_id' => $item->id,
                    'stop_order' => $stop->stop_order,
                    'customer_name' => $stop->order->customer->company_name,
                    'city' => $stop->order->customer->city,
                    'quantity' => $orderedQty,
                    'loaded_quantity' => $boxQty,
                    'is_quantity_corrected' => abs($boxQty - $orderedQty) > 0.001,
                    'unit' => $item->unit,
                    'supplier_name' => $item->supplier_name,
                    'is_whole_chicken' => $item->isWholeChicken(),
                    'ordered_variant_label' => $item->productGramVariant?->displayLabel() ?? '—',
                    'ordered_pieces' => $item->ordered_pieces,
                    'ordered_total_weight_kg' => $item->ordered_total_weight_kg,
                    'variant_weight_kg' => (float) ($item->loaded_total_weight_kg ?? $item->ordered_total_weight_kg),
                    'allows_substitute' => (bool) $item->product?->allows_loading_substitute,
                    'gram_variants' => $item->product?->activeGramVariants ?? collect(),
                ];

                if (filled($item->supplier_name)) {
                    $products[$productId]['supplier_breakdown'][$item->supplier_name] =
                        ($products[$productId]['supplier_breakdown'][$item->supplier_name] ?? 0) + $boxQty;
                }

                $products[$productId]['total'] += $boxQty;

                if ($item->isWholeChicken()) {
                    $effectiveKg = (float) ($item->loaded_total_weight_kg ?? $item->ordered_total_weight_kg);
                    $effectivePieces = (float) ($item->loaded_pieces ?? $item->ordered_pieces);

                    $products[$productId]['total_kg'] += $effectiveKg;
                    $products[$productId]['total_pieces'] += $effectivePieces;

                    $variantKey = $item->product_gram_variant_id ?? 0;

                    if (! isset($products[$productId]['variant_breakdown'][$variantKey])) {
                        $products[$productId]['variant_breakdown'][$variantKey] = [
                            'label' => $item->productGramVariant?->displayLabel() ?? '—',
                            'weight_grams' => (int) ($item->productGramVariant?->weight_grams ?? 0),
                            'boxes' => 0,
                            'pieces' => 0,
                            'kg' => 0,
                            'supplier_breakdown' => [],
                        ];
                    }

                    $products[$productId]['variant_breakdown'][$variantKey]['boxes'] += $boxQty;
                    $products[$productId]['variant_breakdown'][$variantKey]['pieces'] += $effectivePieces;
                    $products[$productId]['variant_breakdown'][$variantKey]['kg'] += $effectiveKg;

                    if (filled($item->supplier_name)) {
                        $products[$productId]['variant_breakdown'][$variantKey]['supplier_breakdown'][$item->supplier_name] =
                            ($products[$productId]['variant_breakdown'][$variantKey]['supplier_breakdown'][$item->supplier_name] ?? 0) + $boxQty;
                    }
                } else {
                    // Eén product kan meerdere verpakkingen hebben; die mogen niet onder
                    // de eenheid van de eerste orderregel samengevoegd worden.
                    $packagingKey = $item->unit ?? '—';

                    if (! isset($products[$productId]['packaging_breakdown'][$packagingKey])) {
                        $products[$productId]['packaging_breakdown'][$packagingKey] = [
                            'label' => $packagingKey,
                            'box_weight_kg' => (float) $item->box_weight_kg,
                            'quantity' => 0,
                            'kg' => 0,
                            'supplier_breakdown' => [],
                        ];
                    }

                    $products[$productId]['packaging_breakdown'][$packagingKey]['quantity'] += $boxQty;
                    $products[$productId]['packaging_breakdown'][$packagingKey]['kg'] += $boxQty * (float) $item->box_weight_kg;

                    if (filled($item->supplier_name)) {
                        $products[$productId]['packaging_breakdown'][$packagingKey]['supplier_breakdown'][$item->supplier_name] =
                            ($products[$productId]['packaging_breakdown'][$packagingKey]['supplier_breakdown'][$item->supplier_name] ?? 0) + $boxQty;
                    }
                }
            }
        }

        foreach ($products as &$product) {
            usort($product['customers'], fn ($a, $b) => $a['stop_order'] <=> $b['stop_order']);

            if (! empty($product['variant_breakdown'])) {
                uasort(
                    $product['variant_breakdown'],
                    fn ($a, $b) => $a['weight_grams'] <=> $b['weight_grams']
                );
                $product['variant_breakdown'] = array_values($product['variant_breakdown']);
            }

            uasort(
                $product['packaging_breakdown'],
                fn ($a, $b) => $a['box_weight_kg'] <=> $b['box_weight_kg']
            );
            $product['packaging_breakdown'] = array_values($product['packaging_breakdown']);
        }

        return array_values($products);
    }

    public function saveLoadedVariant(int $orderItemId): void
    {
        $this->persistLoadedVariant($orderItemId, notifyOnSuccess: true);
    }

    /**
     * Sla de gekozen gramvariant op. Geeft false terug als dat niet mag of de
     * verplichte reden ontbreekt, zodat de chauffeur niet ongemerkt doorgaat.
     */
    private function persistLoadedVariant(int $orderItemId, bool $notifyOnSuccess): bool
    {
        $orderItem = OrderItem::query()
            ->with(['product.activeGramVariants', 'productGramVariant'])
            ->findOrFail($orderItemId);

        if (! $orderItem->isWholeChicken()) {
            return true;
        }

        $data = $this->loadingData[$orderItemId] ?? [];
        $variantId = (int) ($data['loaded_gram_variant_id'] ?? $orderItem->product_gram_variant_id);

        $variant = $orderItem->product?->activeGramVariants->firstWhere('id', $variantId)
            ?? ProductGramVariant::query()->find($variantId);

        if (! $variant) {
            Notification::make()->title('Ongeldige gramvariant')->danger()->send();

            return false;
        }

        $reason = filled($data['substitution_reason'] ?? null)
            ? $data['substitution_reason']
            : null;

        $isSubstitute = $variant->id !== $orderItem->product_gram_variant_id;

        if ($isSubstitute && ! $orderItem->product?->allows_loading_substitute) {
            Notification::make()
                ->title('Alternatief niet toegestaan voor dit product')
                ->warning()
                ->send();

            $this->loadingData[$orderItemId]['loaded_gram_variant_id'] = $orderItem->product_gram_variant_id;

            return false;
        }

        if ($isSubstitute && blank($reason)) {
            Notification::make()
                ->title('Geef een reden op bij een alternatieve variant')
                ->warning()
                ->send();

            return false;
        }

        app(OrderItemLoadingService::class)->recordLoadedVariant($orderItem, $variant, $reason);

        if ($notifyOnSuccess) {
            Notification::make()->title('Geladen variant opgeslagen')->success()->send();
        }

        return true;
    }

    /**
     * Een in de laadkaart gewijzigde gramvariant wordt ook opgeslagen bij
     * doorgaan, niet alleen via de knop "Variant opslaan".
     */
    private function persistChangedVariant(int $orderItemId): bool
    {
        $orderItem = OrderItem::query()->find($orderItemId);
        $selected = $this->loadingData[$orderItemId]['loaded_gram_variant_id'] ?? null;

        if ($orderItem === null || ! $orderItem->isWholeChicken() || blank($selected)) {
            return true;
        }

        $current = $orderItem->loaded_gram_variant_id ?? $orderItem->product_gram_variant_id;

        if ((int) $selected === (int) $current) {
            return true;
        }

        return $this->persistLoadedVariant($orderItemId, notifyOnSuccess: false);
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

    /**
     * Wijkt het ingevoerde laadaantal af van wat besteld is? Wordt live in de
     * laadkaart gebruikt, dus vóórdat de correctie is opgeslagen.
     */
    public function isQuantityCorrected(int $orderItemId, float|int|string $orderedQuantity): bool
    {
        $raw = $this->loadingData[$orderItemId]['loaded_quantity'] ?? null;

        if ($raw === null || trim((string) $raw) === '') {
            return false;
        }

        return abs((float) str_replace(',', '.', (string) $raw) - (float) $orderedQuantity) > 0.001;
    }

    /**
     * Eenheid achter het totaal: één verpakking toont haar naam, bij meerdere
     * verpakkingen is een opgeteld totaal alleen als "verpakkingen" zinvol.
     *
     * @param  array<string, mixed>  $product
     */
    public function totalUnitLabel(array $product): string
    {
        if ($product['is_whole_chicken'] ?? false) {
            return 'doos';
        }

        return count($product['packaging_breakdown'] ?? []) > 1
            ? 'verpakkingen'
            : (string) $product['unit'];
    }

    public function formatBoxes(float|int|string $boxes): string
    {
        return rtrim(rtrim(number_format((float) $boxes, 2, ',', ''), '0'), ',');
    }

    private function saveActualWeightsForCurrentProduct(): bool
    {
        $product = $this->getCurrentProduct();
        $loadingService = app(OrderItemLoadingService::class);

        foreach ($product['customers'] ?? [] as $row) {
            $itemId = $row['order_item_id'];

            if (! $this->persistChangedVariant($itemId)) {
                return false;
            }

            $orderItem = OrderItem::query()
                ->with(['productGramVariant', 'loadedGramVariant'])
                ->findOrFail($itemId);

            $loadingService->recordActualWeight(
                $orderItem,
                $this->decimalInput($this->loadingData[$itemId]['actual_weight_kg'] ?? ''),
            );

            $loadingService->recordLoadedQuantity(
                $orderItem,
                $this->decimalInput($this->loadingData[$itemId]['loaded_quantity'] ?? ''),
            );
        }

        $this->refreshRouteData();

        return true;
    }

    private function decimalInput(mixed $value): ?float
    {
        $raw = trim((string) $value);

        return $raw !== '' ? (float) str_replace(',', '.', $raw) : null;
    }

    /**
     * Herlaad de orderregels zodat gecorrigeerde aantallen direct in de totalen
     * van de laadkaart terugkomen.
     */
    private function refreshRouteData(): void
    {
        $this->route->load([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product.activeGramVariants',
            'routeStops.order.items.productGramVariant',
            'routeStops.order.items.loadedGramVariant',
        ]);

        $this->products = $this->groupByProduct();
    }

    public function nextProduct(): void
    {
        if (! $this->saveActualWeightsForCurrentProduct()) {
            return;
        }

        if ($this->currentProductIndex < count($this->products) - 1) {
            $this->currentProductIndex++;
        }
    }

    public function previousProduct(): void
    {
        if (! $this->saveActualWeightsForCurrentProduct()) {
            return;
        }

        if ($this->currentProductIndex > 0) {
            $this->currentProductIndex--;
        }
    }

    public function finishLoading(): void
    {
        if (! $this->saveActualWeightsForCurrentProduct()) {
            return;
        }

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
