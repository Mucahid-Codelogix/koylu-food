<?php

namespace App\Filament\Driver\Pages;

use App\Models\OrderItem;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Services\OrderItemLoadingService;
use App\Services\RouteWorkflowService;
use App\Services\WholeChickenBoxBreakdownService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class DriverLoadingPhase extends Page
{
    protected string $view = 'filament.driver.pages.driver-loading-phase';

    protected static ?string $title = 'Laden';

    /** Geen paginakop: de chauffeur heeft elke pixel nodig voor de laadlijst. */
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected static bool $shouldRegisterNavigation = false;

    public Route $route;

    public int $currentProductIndex = 0;

    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    /** @var array<int, array{loaded_gram_variant_id: int|null, substitution_reason: string, actual_weight_kg: string}> */
    public array $loadingData = [];

    /**
     * Openstaande doosverdelingen van hele kip, per orderregel.
     *
     * @var array<int, array<int, array{quantity: string, variant_id: int|null, weight_kg: string}>>
     */
    public array $boxBreakdowns = [];

    /**
     * Per orderregel: heeft de chauffeur de verdeling aangeraakt? Alleen dan
     * wordt er bij doorgaan iets opgeslagen.
     *
     * @var array<int, bool>
     */
    public array $breakdownTouched = [];

    /**
     * Tijdstip van de laatste opslag per regel; alleen voor het vinkje "opgeslagen".
     *
     * @var array<int, int>
     */
    public array $savedRows = [];

    /** Reserveregels die klaarstaan voor "Regel toevoegen", zonder serververzoek. */
    public const SPARE_BREAKDOWN_ROWS = 4;

    public function mount(): void
    {
        $routeId = request()->query('routeId') ?? request()->route('routeId');

        $this->route = Route::with([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product.activeGramVariants.productSuppliers',
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
        $reasons = array_map(fn (array $data): string => (string) ($data['substitution_reason'] ?? ''), $this->loadingData);
        $this->loadingData = [];

        foreach ($this->route->routeStops as $stop) {
            foreach ($stop->order->items as $item) {
                $this->loadingData[$item->id] = [
                    'loaded_gram_variant_id' => $item->loaded_gram_variant_id
                        ?? $item->product_gram_variant_id,
                    'substitution_reason' => $item->loading_substitution_reason
                        ?? ($reasons[$item->id] ?? ''),
                    'actual_weight_kg' => $item->loaded_actual_weight_kg !== null
                        ? (string) $item->loaded_actual_weight_kg
                        : '',
                    'loaded_quantity' => (string) (float) ($item->loaded_packaging_quantity ?? $item->quantity),
                ];

                if ($item->isWholeChicken() && $item->split_from_order_item_id === null) {
                    $this->boxBreakdowns[$item->id] = $this->defaultBoxRows($item);
                    $this->breakdownTouched[$item->id] = false;
                }
            }
        }
    }

    /**
     * De verdeling zoals die nu geladen is, aangevuld met lege reserveregels
     * zodat "Regel toevoegen" in de browser kan zonder serververzoek.
     *
     * @return array<int, array{quantity: string, variant_id: int|null, weight_kg: string}>
     */
    private function defaultBoxRows(OrderItem $orderItem): array
    {
        $splits = app(WholeChickenBoxBreakdownService::class)->splitsOf($orderItem);
        $rows = [];

        if ($splits->isNotEmpty()) {
            foreach (collect([$orderItem])->concat($splits) as $part) {
                $rows[] = [
                    'quantity' => $this->formatBoxes((float) ($part->loaded_packaging_quantity ?? $part->quantity)),
                    'variant_id' => $part->loaded_gram_variant_id ?? $part->product_gram_variant_id,
                    'weight_kg' => $part->loaded_actual_weight_kg !== null
                        ? (string) (float) $part->loaded_actual_weight_kg
                        : '',
                ];
            }
        } else {
            $variantId = $orderItem->loaded_gram_variant_id ?? $orderItem->product_gram_variant_id;
            $boxes = max(1, (int) ceil((float) ($orderItem->loaded_packaging_quantity ?? $orderItem->quantity)));

            for ($i = 0; $i < $boxes; $i++) {
                $rows[] = [
                    'quantity' => '1',
                    'variant_id' => $variantId,
                    'weight_kg' => $orderItem->loaded_actual_weight_kg !== null && $boxes === 1
                        ? (string) (float) $orderItem->loaded_actual_weight_kg
                        : '',
                ];
            }
        }

        $variantId = $orderItem->loaded_gram_variant_id ?? $orderItem->product_gram_variant_id;

        for ($i = 0; $i < self::SPARE_BREAKDOWN_ROWS; $i++) {
            $rows[] = [
                'quantity' => '0',
                'variant_id' => $variantId,
                'weight_kg' => '',
            ];
        }

        return $rows;
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
                        'loading_sort_order' => $item->product?->loading_sort_order,
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
                    'loaded_variant_label' => $item->effectiveGramVariantLabel(),
                    'loaded_variant_id' => $item->loaded_gram_variant_id ?? $item->product_gram_variant_id,
                    'is_substituted' => $item->loaded_gram_variant_id !== null
                        && $item->loaded_gram_variant_id !== $item->product_gram_variant_id,
                    'split_from' => $item->split_from_order_item_id,
                    'is_checked' => $item->loading_checked_at !== null,
                    'ordered_variant_id' => $item->product_gram_variant_id,
                    'loaded_rows' => [[
                        'variant_id' => $item->loaded_gram_variant_id ?? $item->product_gram_variant_id,
                        'label' => $item->effectiveGramVariantLabel(),
                        'boxes' => $boxQty,
                        'weight_kg' => $item->loaded_actual_weight_kg !== null
                            ? (float) $item->loaded_actual_weight_kg
                            : null,
                        'is_substituted' => $item->loaded_gram_variant_id !== null
                            && $item->loaded_gram_variant_id !== $item->product_gram_variant_id,
                    ]],
                    'actual_weight_kg' => $item->loaded_actual_weight_kg !== null
                        ? (float) $item->loaded_actual_weight_kg
                        : null,
                    'ordered_pieces' => $item->ordered_pieces,
                    'ordered_total_weight_kg' => $item->ordered_total_weight_kg,
                    'variant_weight_kg' => (float) ($item->loaded_total_weight_kg ?? $item->ordered_total_weight_kg),
                    'allows_substitute' => (bool) $item->product?->allows_loading_substitute,
                    'gram_variants' => $this->gramVariantsForOrderItem($item),
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

                    // Wat er geladen moet worden is de geladen variant, niet de bestelde.
                    $effectiveVariant = $item->loadedGramVariant ?? $item->productGramVariant;
                    $variantKey = $effectiveVariant?->id ?? 0;

                    if (! isset($products[$productId]['variant_breakdown'][$variantKey])) {
                        $products[$productId]['variant_breakdown'][$variantKey] = [
                            'variant_id' => $effectiveVariant?->id,
                            'label' => $effectiveVariant?->displayLabel() ?? '—',
                            'weight_grams' => (int) ($effectiveVariant?->weight_grams ?? 0),
                            'boxes' => 0,
                            'pieces' => 0,
                            'kg' => 0,
                            'supplier_breakdown' => [],
                            'substituted_from' => [],
                        ];
                    }

                    // Wat er geladen wordt in plaats van de bestelde variant blijft
                    // zichtbaar, anders verdwijnt de oorspronkelijke bestelling uit beeld.
                    if ($item->loaded_gram_variant_id !== null
                        && $item->loaded_gram_variant_id !== $item->product_gram_variant_id) {
                        $orderedLabel = $item->productGramVariant?->displayLabel() ?? '—';

                        $products[$productId]['variant_breakdown'][$variantKey]['substituted_from'][$orderedLabel] =
                            ($products[$productId]['variant_breakdown'][$variantKey]['substituted_from'][$orderedLabel] ?? 0) + $boxQty;
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
            $product['customers'] = $this->mergeSplitRows($product['customers']);
            $product['has_mixed_suppliers'] = count($product['supplier_breakdown']) > 1;
            $product['checked_count'] = count(array_filter(
                $product['customers'],
                fn (array $row): bool => $row['is_checked'],
            ));
            $product['single_supplier'] = count($product['supplier_breakdown']) === 1
                ? array_key_first($product['supplier_breakdown'])
                : null;

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

        // Laadvolgorde zoals de admin die instelt (Producten → Laadvolgorde), niet de toevallige
        // volgorde waarin producten in de stops voorkomen.
        $products = array_values($products);

        usort(
            $products,
            fn (array $a, array $b): int => [$a['loading_sort_order'] ?? PHP_INT_MAX, $a['name']]
                <=> [$b['loading_sort_order'] ?? PHP_INT_MAX, $b['name']]
        );

        return $products;
    }

    /**
     * Gesplitste regels horen bij de bestelde regel waar ze uit komen en worden
     * daar als geladen verdeling onder getoond.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mergeSplitRows(array $rows): array
    {
        $indexById = [];

        foreach ($rows as $index => $row) {
            $indexById[$row['order_item_id']] = $index;
        }

        foreach ($rows as $index => $row) {
            $parentId = $row['split_from'] ?? null;

            if ($parentId === null || ! isset($indexById[$parentId])) {
                continue;
            }

            $parentIndex = $indexById[$parentId];

            $rows[$parentIndex]['quantity'] += $row['quantity'];
            $rows[$parentIndex]['loaded_quantity'] += $row['loaded_quantity'];
            $rows[$parentIndex]['ordered_pieces'] += $row['ordered_pieces'];
            $rows[$parentIndex]['ordered_total_weight_kg'] += $row['ordered_total_weight_kg'];
            $rows[$parentIndex]['loaded_rows'] = array_merge(
                $rows[$parentIndex]['loaded_rows'],
                $row['loaded_rows'],
            );
            $rows[$parentIndex]['is_checked'] = $rows[$parentIndex]['is_checked'] && $row['is_checked'];

            unset($rows[$index]);
        }

        return array_values($rows);
    }

    public function saveLoadedVariant(int $orderItemId): void
    {
        $this->persistLoadedVariant($orderItemId, notifyOnSuccess: true);
    }

    /**
     * Aantal bestelde dozen van de regel, inclusief eerder afgesplitste dozen.
     */
    public function orderedBoxes(int $orderItemId): float
    {
        foreach ($this->products as $product) {
            foreach ($product['customers'] ?? [] as $row) {
                if ($row['order_item_id'] === $orderItemId) {
                    return (float) $row['quantity'];
                }
            }
        }

        return 0.0;
    }

    /**
     * Plus en min op het geladen aantal van een gewoon product, zodat de
     * chauffeur niet hoeft te typen.
     */
    public function stepLoadedQuantity(int $orderItemId, int $step): void
    {
        $current = $this->decimalInput($this->loadingData[$orderItemId]['loaded_quantity'] ?? '') ?? 0;

        $this->loadingData[$orderItemId]['loaded_quantity'] = $this->formatBoxes(max(0, $current + $step));

        $this->saveRow($orderItemId);
    }

    public function assignedBoxes(int $orderItemId): float
    {
        return collect($this->boxBreakdowns[$orderItemId] ?? [])
            ->sum(fn (array $row): float => (float) ($this->decimalInput($row['quantity'] ?? '') ?? 0));
    }

    /**
     * Regels van gewone producten worden meteen opgeslagen zodra de chauffeur
     * het veld verlaat, zodat werk niet verloren gaat als de telefoon wegvalt.
     */
    public function saveRow(int $orderItemId): void
    {
        $orderItem = OrderItem::query()
            ->with(['productGramVariant', 'loadedGramVariant'])
            ->findOrFail($orderItemId);

        $loadingService = app(OrderItemLoadingService::class);

        $loadingService->recordActualWeight(
            $orderItem,
            $this->decimalInput($this->loadingData[$orderItemId]['actual_weight_kg'] ?? ''),
        );

        $loadingService->recordLoadedQuantity(
            $orderItem->fresh(),
            $this->decimalInput($this->loadingData[$orderItemId]['loaded_quantity'] ?? ''),
        );

        $this->savedRows[$orderItemId] = now()->getTimestampMs();

        $this->refreshRouteData();
    }

    public function saveBoxBreakdown(int $orderItemId): void
    {
        if ($this->persistBoxBreakdown($orderItemId, notifyOnSuccess: true)) {
            $this->breakdownTouched[$orderItemId] = false;
            $this->refreshRouteData();
        }
    }

    /**
     * Slaat de doosverdeling op en splitst de orderregel als er meerdere
     * varianten geladen zijn. Geeft false terug zodra er iets niet klopt.
     */
    private function persistBoxBreakdown(int $orderItemId, bool $notifyOnSuccess): bool
    {
        $rows = $this->boxBreakdowns[$orderItemId] ?? null;

        if ($rows === null) {
            return true;
        }

        $orderItem = OrderItem::query()
            ->with(['product.activeGramVariants.productSuppliers', 'productGramVariant'])
            ->findOrFail($orderItemId);

        if (! $orderItem->isWholeChicken()) {
            return true;
        }

        $variants = $this->gramVariantsForOrderItem($orderItem);
        $prepared = [];
        $hasSubstitute = false;

        foreach ($rows as $row) {
            $quantity = $this->decimalInput($row['quantity'] ?? '') ?? 0;

            if ($quantity <= 0) {
                continue;
            }

            $variant = $variants->firstWhere('id', (int) ($row['variant_id'] ?? 0));

            if (! $variant) {
                Notification::make()->title('Kies bij elke doos een geldige gramvariant')->danger()->send();

                return false;
            }

            if ($variant->id !== $orderItem->product_gram_variant_id) {
                $hasSubstitute = true;
            }

            $prepared[] = [
                'quantity' => $quantity,
                'variant' => $variant,
                'weight_kg' => $this->decimalInput($row['weight_kg'] ?? ''),
            ];
        }

        if ($prepared === []) {
            Notification::make()->title('Vul minstens één doos in')->warning()->send();

            return false;
        }

        $reason = filled($this->loadingData[$orderItemId]['substitution_reason'] ?? null)
            ? $this->loadingData[$orderItemId]['substitution_reason']
            : null;

        if ($hasSubstitute && ! $orderItem->product?->allows_loading_substitute) {
            Notification::make()->title('Alternatieve variant niet toegestaan voor dit product')->warning()->send();

            return false;
        }

        if ($hasSubstitute && blank($reason)) {
            Notification::make()->title('Geef een reden op bij een alternatieve variant')->warning()->send();

            return false;
        }

        app(WholeChickenBoxBreakdownService::class)->apply($orderItem, $prepared, $reason);

        if ($notifyOnSuccess) {
            Notification::make()->title('Laden opgeslagen')->success()->send();
        }

        return true;
    }

    /**
     * Sla de gekozen gramvariant op. Geeft false terug als dat niet mag of de
     * verplichte reden ontbreekt, zodat de chauffeur niet ongemerkt doorgaat.
     */
    /**
     * Gramvarianten die de leverancier van deze regel kan leveren.
     *
     * @return Collection<int, ProductGramVariant>
     */
    private function gramVariantsForOrderItem(OrderItem $item): Collection
    {
        $variants = $item->product?->activeGramVariants ?? collect();

        return $variants
            ->filter(fn (ProductGramVariant $variant): bool => $variant->isAvailableForProductSupplier($item->product_supplier_id))
            ->values();
    }

    private function persistLoadedVariant(int $orderItemId, bool $notifyOnSuccess): bool
    {
        $orderItem = OrderItem::query()
            ->with(['product.activeGramVariants.productSuppliers', 'productGramVariant'])
            ->findOrFail($orderItemId);

        if (! $orderItem->isWholeChicken()) {
            return true;
        }

        $data = $this->loadingData[$orderItemId] ?? [];
        $variantId = (int) ($data['loaded_gram_variant_id'] ?? $orderItem->product_gram_variant_id);

        $variant = $this->gramVariantsForOrderItem($orderItem)->firstWhere('id', $variantId)
            ?? ($variantId === $orderItem->product_gram_variant_id
                ? ProductGramVariant::query()->find($variantId)
                : null);

        if (! $variant) {
            Notification::make()->title('Ongeldige gramvariant voor deze leverancier')->danger()->send();

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

    /**
     * Regels van hele kip gebundeld per klant, zoals de chauffeur ze op de kar ziet.
     *
     * @param  array<string, mixed>  $product
     * @return array<int, array{stop_order: int, customer_name: string, city: ?string, rows: array<int, array<string, mixed>>}>
     */
    public function customerGroups(array $product): array
    {
        $groups = [];

        foreach ($product['customers'] ?? [] as $row) {
            $key = $row['stop_order'].'-'.$row['customer_name'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'stop_order' => $row['stop_order'],
                    'customer_name' => $row['customer_name'],
                    'city' => $row['city'],
                    'rows' => [],
                ];
            }

            $groups[$key]['rows'][] = $row;
        }

        return array_values($groups);
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

            if (! empty($this->breakdownTouched[$itemId])) {
                if (! $this->persistBoxBreakdown($itemId, notifyOnSuccess: false)) {
                    return false;
                }

                $this->breakdownTouched[$itemId] = false;

                continue;
            }

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
            'routeStops.order.items.product.activeGramVariants.productSuppliers',
            'routeStops.order.items.productGramVariant',
            'routeStops.order.items.loadedGramVariant',
        ]);

        $this->initLoadingData();
        $this->products = $this->groupByProduct();
    }

    /**
     * Vinkt een regel af als geladen, inclusief de eventueel afgesplitste dozen.
     */
    public function toggleRowChecked(int $orderItemId): void
    {
        $orderItem = OrderItem::query()->findOrFail($orderItemId);
        $checkedAt = $orderItem->loading_checked_at === null ? now() : null;

        $orderItem->update(['loading_checked_at' => $checkedAt]);

        OrderItem::query()
            ->where('split_from_order_item_id', $orderItem->id)
            ->update(['loading_checked_at' => $checkedAt]);

        $this->markRowChecked($orderItemId, $checkedAt !== null);
    }

    /**
     * Alleen deze regel bijwerken in plaats van de hele laadkaart opnieuw op te
     * bouwen: dat houdt de pagina rustig en voorkomt dat rijen verspringen.
     */
    private function markRowChecked(int $orderItemId, bool $isChecked): void
    {
        foreach ($this->products as $productIndex => $product) {
            $changed = false;

            foreach ($product['customers'] ?? [] as $rowIndex => $row) {
                if ($row['order_item_id'] !== $orderItemId) {
                    continue;
                }

                $this->products[$productIndex]['customers'][$rowIndex]['is_checked'] = $isChecked;
                $changed = true;
            }

            if ($changed) {
                $this->products[$productIndex]['checked_count'] = count(array_filter(
                    $this->products[$productIndex]['customers'],
                    fn (array $row): bool => $row['is_checked'],
                ));
            }
        }
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
