<?php

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Services\OrderItemSnapshotBuilder;
use App\Services\ProductPricingService;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    public Collection $products;

    public array $cart = [];

    public float $total = 0;

    public bool $cartOpen = false;

    public Collection $suppliers;

    public ?int $selectedSupplier = null;

    public string $search = '';

    /** @var array<int, array{packaging_id?: ?int, gram_variant_id?: ?int, supplier_id?: ?int}> */
    public array $selections = [];

    /** @var array<int, float> */
    public array $quantities = [];

    public bool $confirmOrderModalOpen = false;

    public function mount(): void
    {
        $this->products = collect();
        $this->suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();
        $this->loadProducts();
    }

    public function loadProducts(): void
    {
        $this->products = Product::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('product_type', ProductType::Standard)
                        ->whereHas('activePackagings');
                })->orWhere(function ($query): void {
                    $query->where('product_type', ProductType::WholeChicken)
                        ->whereHas('activeGramVariants');
                });
            })
            ->whereHas('activeProductSuppliers')
            ->when($this->selectedSupplier, fn ($q) => $q->whereHas(
                'activeProductSuppliers',
                fn ($q) => $q->where('supplier_id', $this->selectedSupplier)
            ))
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->with(['activePackagings', 'activeGramVariants', 'activeProductSuppliers.supplier'])
            ->orderBy('name')
            ->get();

        $this->initializeSelections();
    }

    public function updatedSelectedSupplier(): void
    {
        $this->loadProducts();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->selectedSupplier = null;
        $this->loadProducts();
    }

    public function continueShopping(): void
    {
        $this->cartOpen = false;
    }

    public function toggleCart(): void
    {
        $this->cartOpen = ! $this->cartOpen;
    }

    public function addToCart(int $productId): void
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return;
        }

        $productSupplier = $this->resolveProductSupplier($product);

        if (! $productSupplier) {
            Notification::make()->title('Selecteer een leverancier')->warning()->send();

            return;
        }

        if ($product->isWholeChicken()) {
            $gramVariant = $this->resolveGramVariant($product);

            if (! $gramVariant) {
                Notification::make()->title('Selecteer een gramvariant')->warning()->send();

                return;
            }

            $min = (float) ($product->min_order_quantity ?? 1);
            $qty = max($min, (float) ($this->quantities[$productId] ?? $min));
            $preview = $this->buildWholeChickenPreview($product, $gramVariant, $productSupplier, $qty);
            $cartKey = $this->cartKey($product->id, $productSupplier->id, $gramVariant->id);

            if (isset($this->cart[$cartKey])) {
                $this->cart[$cartKey]['quantity'] += $qty;
                $this->refreshWholeChickenCartLine($this->cart[$cartKey], $gramVariant, $productSupplier);
            } else {
                $this->cart[$cartKey] = $this->makeWholeChickenCartLine($product, $gramVariant, $productSupplier, $preview, $qty, $cartKey);
            }

            $this->quantities[$productId] = $this->cart[$cartKey]['quantity'];
        } else {
            $packaging = $this->resolvePackaging($product);

            if (! $packaging) {
                Notification::make()->title('Selecteer een verpakking')->warning()->send();

                return;
            }

            $min = (float) ($product->min_order_quantity ?? 1);
            $qty = max($min, (float) ($this->quantities[$productId] ?? $min));
            $preview = $this->buildStandardPreview($product, $packaging, $productSupplier, $qty);
            $cartKey = $this->cartKey($product->id, $productSupplier->id, null, $packaging->id);

            if (isset($this->cart[$cartKey])) {
                $this->cart[$cartKey]['quantity'] += $qty;
                $this->cart[$cartKey]['subtotal'] = $this->pricing()->lineSubtotal(
                    $this->customer(),
                    $productSupplier,
                    $packaging,
                    $this->cart[$cartKey]['quantity'],
                );
                $this->cart[$cartKey]['ordered_pieces'] = null;
                $this->cart[$cartKey]['ordered_total_weight_kg'] = $this->pricing()->totalWeightKg(
                    $packaging,
                    $this->cart[$cartKey]['quantity'],
                );
            } else {
                $this->cart[$cartKey] = $this->makeStandardCartLine($product, $packaging, $productSupplier, $preview, $qty, $cartKey);
            }

            $this->quantities[$productId] = $this->cart[$cartKey]['quantity'];
        }

        $this->recalculateTotal();
    }

    public function incrementProductQuantity(int $productId): void
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return;
        }

        $cartKey = $this->cartKeyForCurrentSelection($productId);

        if ($cartKey !== null && isset($this->cart[$cartKey])) {
            $this->updateQuantity($cartKey, $this->cart[$cartKey]['quantity'] + 1);
            $this->quantities[$productId] = $this->cart[$cartKey]['quantity'];

            return;
        }

        $min = $this->defaultQuantityForProduct($product);
        $this->quantities[$productId] = max($min, ($this->quantities[$productId] ?? $min) + 1);
    }

    public function decrementProductQuantity(int $productId): void
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return;
        }

        $min = $this->defaultQuantityForProduct($product);
        $cartKey = $this->cartKeyForCurrentSelection($productId);

        if ($cartKey !== null && isset($this->cart[$cartKey])) {
            $newQty = $this->cart[$cartKey]['quantity'] - 1;

            if ($newQty < $min) {
                $this->removeFromCart($cartKey);
                $this->quantities[$productId] = $min;
            } else {
                $this->updateQuantity($cartKey, $newQty);
                $this->quantities[$productId] = $newQty;
            }

            return;
        }

        $this->quantities[$productId] = max($min, ($this->quantities[$productId] ?? $min) - 1);
    }

    public function incrementCartLine(string $cartKey): void
    {
        if (! isset($this->cart[$cartKey])) {
            return;
        }

        $this->updateQuantity($cartKey, $this->cart[$cartKey]['quantity'] + 1);
        $this->syncQuantityFromCartKey($cartKey);
    }

    public function decrementCartLine(string $cartKey): void
    {
        if (! isset($this->cart[$cartKey])) {
            return;
        }

        $min = (float) ($this->cart[$cartKey]['min_quantity'] ?? 1);
        $newQty = $this->cart[$cartKey]['quantity'] - 1;

        if ($newQty < $min) {
            $this->removeFromCart($cartKey);
        } else {
            $this->updateQuantity($cartKey, $newQty);
        }

        $this->syncQuantityFromCartKey($cartKey);
    }

    public function requestPlaceOrder(): void
    {
        if ($this->cart === []) {
            Notification::make()->title('Winkelwagen is leeg')->warning()->send();

            return;
        }

        $this->confirmOrderModalOpen = true;
    }

    public function cancelPlaceOrder(): void
    {
        $this->confirmOrderModalOpen = false;
    }

    public function confirmPlaceOrder(): void
    {
        $this->confirmOrderModalOpen = false;
        $this->placeOrder();
    }

    public function cardQuantity(int $productId): float
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return 1;
        }

        $cartKey = $this->cartKeyForCurrentSelection($productId);

        if ($cartKey !== null && isset($this->cart[$cartKey])) {
            return (float) $this->cart[$cartKey]['quantity'];
        }

        return (float) ($this->quantities[$productId] ?? $this->defaultQuantityForProduct($product));
    }

    public function removeFromCart(string $cartKey): void
    {
        unset($this->cart[$cartKey]);
        $this->recalculateTotal();
    }

    public function updateQuantity(string $cartKey, mixed $quantity): void
    {
        if (! isset($this->cart[$cartKey])) {
            return;
        }

        $item = $this->cart[$cartKey];
        $productSupplier = ProductSupplier::query()->find($item['product_supplier_id']);

        if (! $productSupplier) {
            return;
        }

        $qty = max((float) ($item['min_quantity'] ?? 1), (float) $quantity);
        $this->cart[$cartKey]['quantity'] = $qty;

        if ($item['is_whole_chicken'] ?? false) {
            $gramVariant = ProductGramVariant::query()->find($item['product_gram_variant_id']);

            if (! $gramVariant) {
                return;
            }

            $this->refreshWholeChickenCartLine($this->cart[$cartKey], $gramVariant, $productSupplier);
        } else {
            $packaging = ProductPackaging::query()->find($item['product_packaging_id']);

            if (! $packaging) {
                return;
            }

            $this->cart[$cartKey]['subtotal'] = $this->pricing()->lineSubtotal(
                $this->customer(),
                $productSupplier,
                $packaging,
                $qty,
            );
            $this->cart[$cartKey]['ordered_total_weight_kg'] = $this->pricing()->totalWeightKg($packaging, $qty);
        }

        $this->recalculateTotal();
    }

    public function productPreview(int $productId): array
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return $this->emptyPreview();
        }

        $productSupplier = $this->resolveProductSupplier($product);

        if (! $productSupplier) {
            return $this->emptyPreview();
        }

        if ($product->isWholeChicken()) {
            $gramVariant = $this->resolveGramVariant($product);

            if (! $gramVariant) {
                return $this->emptyPreview();
            }

            $qty = (float) ($this->quantities[$productId] ?? $this->defaultQuantityForProduct($product));

            return $this->buildWholeChickenPreview($product, $gramVariant, $productSupplier, $qty);
        }

        $packaging = $this->resolvePackaging($product);

        if (! $packaging) {
            return $this->emptyPreview();
        }

        $qty = (float) ($this->quantities[$productId] ?? $this->defaultQuantityForProduct($product));

        return $this->buildStandardPreview($product, $packaging, $productSupplier, $qty);
    }

    public function productInCart(int $productId): bool
    {
        return collect($this->cart)->contains(fn (array $item): bool => $item['product_id'] === $productId);
    }

    public function productCartSubtotal(int $productId): float
    {
        return (float) collect($this->cart)
            ->where('product_id', $productId)
            ->sum('subtotal');
    }

    public function placeOrder(): void
    {
        if ($this->cart === []) {
            Notification::make()->title('Winkelwagen is leeg')->danger()->send();

            return;
        }

        foreach ($this->cart as $item) {
            if ($item['quantity'] < ($item['min_quantity'] ?? 1)) {
                Notification::make()
                    ->title("Minimaal {$item['min_quantity']} vereist voor {$item['name']}")
                    ->warning()
                    ->send();

                return;
            }
        }

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $order = Order::create([
                'order_number' => 'ORD-'.strtoupper(Str::random(6)),
                'customer_id' => $user->customer_id,
                'status' => OrderStatus::PLACED,
                'order_date' => now(),
                'delivery_date' => now()->addDay(),
                'total_price' => $this->total,
            ]);

            $snapshotBuilder = app(OrderItemSnapshotBuilder::class);
            $customer = $this->customer();

            foreach ($this->cart as $item) {
                if ($item['is_whole_chicken'] ?? false) {
                    $attributes = $snapshotBuilder->fromWholeChickenLine(
                        Product::query()->findOrFail($item['product_id']),
                        ProductGramVariant::query()->findOrFail($item['product_gram_variant_id']),
                        ProductSupplier::query()->findOrFail($item['product_supplier_id']),
                        (float) $item['quantity'],
                        $customer,
                    );
                } else {
                    $attributes = $snapshotBuilder->fromStandardLine(
                        Product::query()->findOrFail($item['product_id']),
                        ProductPackaging::query()->findOrFail($item['product_packaging_id']),
                        ProductSupplier::query()->findOrFail($item['product_supplier_id']),
                        (float) $item['quantity'],
                        $customer,
                    );
                }

                OrderItem::create(array_merge(['order_id' => $order->id], $attributes));
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            Notification::make()->title('Er ging iets fout bij het plaatsen van de bestelling')->danger()->send();

            return;
        }

        try {
            $order->load('items');
            Mail::to($user->email)->send(new OrderPlacedMail($order));
        } catch (\Throwable $e) {
            report($e);
        }

        $this->cart = [];
        $this->total = 0;
        $this->cartOpen = false;
        $this->confirmOrderModalOpen = false;
        $this->quantities = [];

        Notification::make()->title('Bestelling geplaatst!')->success()->send();
        $this->loadProducts();
    }

    private function initializeSelections(): void
    {
        foreach ($this->products as $product) {
            $productSupplier = $this->resolveProductSupplier($product);

            if ($product->isWholeChicken()) {
                $gramVariantId = $this->selections[$product->id]['gram_variant_id']
                    ?? $product->defaultGramVariant()?->id;

                $this->selections[$product->id] = [
                    'gram_variant_id' => $gramVariantId,
                    'supplier_id' => $productSupplier?->id,
                ];
            } else {
                $packagingId = $this->selections[$product->id]['packaging_id']
                    ?? $product->defaultPackaging()?->id;

                $this->selections[$product->id] = [
                    'packaging_id' => $packagingId,
                    'supplier_id' => $productSupplier?->id,
                ];
            }

            if (! isset($this->quantities[$product->id])) {
                $this->quantities[$product->id] = $this->defaultQuantityForProduct($product);
            }
        }
    }

    private function defaultQuantityForProduct(Product $product): float
    {
        return max(1, (float) ($product->min_order_quantity ?? 1));
    }

    private function cartKeyForCurrentSelection(int $productId): ?string
    {
        $product = $this->products->firstWhere('id', $productId);

        if (! $product) {
            return null;
        }

        $productSupplier = $this->resolveProductSupplier($product);

        if (! $productSupplier) {
            return null;
        }

        if ($product->isWholeChicken()) {
            $gramVariant = $this->resolveGramVariant($product);

            if (! $gramVariant) {
                return null;
            }

            return $this->cartKey($productId, $productSupplier->id, $gramVariant->id);
        }

        $packaging = $this->resolvePackaging($product);

        if (! $packaging) {
            return null;
        }

        return $this->cartKey($productId, $productSupplier->id, null, $packaging->id);
    }

    private function syncQuantityFromCartKey(string $cartKey): void
    {
        if (! isset($this->cart[$cartKey])) {
            return;
        }

        $this->quantities[$this->cart[$cartKey]['product_id']] = (float) $this->cart[$cartKey]['quantity'];
    }

    private function resolvePackaging(Product $product): ?ProductPackaging
    {
        $packagingId = $this->selections[$product->id]['packaging_id'] ?? null;

        return $product->activePackagings->firstWhere('id', $packagingId)
            ?? $product->defaultPackaging();
    }

    private function resolveGramVariant(Product $product): ?ProductGramVariant
    {
        $gramVariantId = $this->selections[$product->id]['gram_variant_id'] ?? null;

        return $product->activeGramVariants->firstWhere('id', $gramVariantId)
            ?? $product->defaultGramVariant();
    }

    private function resolveProductSupplier(Product $product): ?ProductSupplier
    {
        $productSupplierId = $this->selections[$product->id]['supplier_id'] ?? null;

        if ($productSupplierId) {
            $offer = $product->activeProductSuppliers->firstWhere('id', $productSupplierId);

            if ($offer) {
                return $offer;
            }
        }

        if ($this->selectedSupplier) {
            $offer = $product->activeProductSuppliers
                ->firstWhere('supplier_id', $this->selectedSupplier);

            if ($offer) {
                return $offer;
            }
        }

        return $product->defaultProductSupplier();
    }

    private function buildStandardPreview(
        Product $product,
        ProductPackaging $packaging,
        ProductSupplier $productSupplier,
        float|int $quantity = 1,
    ): array {
        $pricing = $this->pricing();
        $customer = $this->customer();
        $pricePerKg = $pricing->pricePerKg($customer, $productSupplier);
        $unitPrice = $pricing->unitPricePerPackaging($customer, $productSupplier, $packaging);
        $qty = (float) $quantity;
        $min = (float) ($packaging->min_order_quantity ?? $product->min_order_quantity ?? 1);

        return [
            'is_whole_chicken' => false,
            'packaging_label' => $packaging->displayLabel(),
            'price_per_kg' => $pricePerKg,
            'unit_price' => $unitPrice,
            'weight_kg' => (float) $packaging->weight_kg,
            'total_weight_kg' => $pricing->totalWeightKg($packaging, $qty),
            'ordered_pieces' => null,
            'min_quantity' => $min,
            'subtotal' => $pricing->lineSubtotal($customer, $productSupplier, $packaging, $qty),
            'supplier_name' => $productSupplier->supplier->name,
        ];
    }

    private function buildWholeChickenPreview(
        Product $product,
        ProductGramVariant $gramVariant,
        ProductSupplier $productSupplier,
        float|int $quantity = 1,
    ): array {
        $pricing = $this->pricing();
        $customer = $this->customer();
        $qty = (float) $quantity;
        $min = (float) ($product->min_order_quantity ?? 1);

        return [
            'is_whole_chicken' => true,
            'packaging_label' => $gramVariant->boxDescription(),
            'gram_variant_label' => $gramVariant->displayLabel(),
            'price_per_kg' => $pricing->pricePerKg($customer, $productSupplier),
            'unit_price' => $pricing->unitPricePerBoxForGramVariant($customer, $productSupplier, $gramVariant),
            'weight_kg' => (float) $gramVariant->box_weight_kg,
            'total_weight_kg' => $gramVariant->calculateTotalWeightKg($qty),
            'ordered_pieces' => $gramVariant->calculateOrderedPieces($qty),
            'pieces_per_box' => $gramVariant->pieces_per_box,
            'min_quantity' => $min,
            'subtotal' => $pricing->lineSubtotalForGramVariant($customer, $productSupplier, $gramVariant, $qty),
            'supplier_name' => $productSupplier->supplier->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function makeStandardCartLine(
        Product $product,
        ProductPackaging $packaging,
        ProductSupplier $productSupplier,
        array $preview,
        float $quantity,
        string $cartKey,
    ): array {
        return [
            'cart_key' => $cartKey,
            'is_whole_chicken' => false,
            'product_id' => $product->id,
            'product_packaging_id' => $packaging->id,
            'product_gram_variant_id' => null,
            'product_supplier_id' => $productSupplier->id,
            'supplier_id' => $productSupplier->supplier_id,
            'supplier_name' => $productSupplier->supplier->name,
            'packaging_label' => $preview['packaging_label'],
            'weight_kg' => $preview['weight_kg'],
            'price_per_kg' => $preview['price_per_kg'],
            'unit_price' => $preview['unit_price'],
            'quantity' => $quantity,
            'ordered_pieces' => null,
            'ordered_total_weight_kg' => $preview['total_weight_kg'],
            'subtotal' => $preview['subtotal'],
            'name' => $product->name,
            'unit' => $preview['packaging_label'],
            'image_path' => $product->image_path,
            'min_quantity' => $preview['min_quantity'],
        ];
    }

    /**
     * @param  array<string, mixed>  $preview
     * @return array<string, mixed>
     */
    private function makeWholeChickenCartLine(
        Product $product,
        ProductGramVariant $gramVariant,
        ProductSupplier $productSupplier,
        array $preview,
        float $quantity,
        string $cartKey,
    ): array {
        return [
            'cart_key' => $cartKey,
            'is_whole_chicken' => true,
            'product_id' => $product->id,
            'product_packaging_id' => null,
            'product_gram_variant_id' => $gramVariant->id,
            'product_supplier_id' => $productSupplier->id,
            'supplier_id' => $productSupplier->supplier_id,
            'supplier_name' => $productSupplier->supplier->name,
            'packaging_label' => $preview['packaging_label'],
            'gram_variant_label' => $preview['gram_variant_label'],
            'weight_kg' => $preview['weight_kg'],
            'pieces_per_box' => $preview['pieces_per_box'],
            'price_per_kg' => $preview['price_per_kg'],
            'unit_price' => $preview['unit_price'],
            'quantity' => $quantity,
            'ordered_pieces' => $preview['ordered_pieces'],
            'ordered_total_weight_kg' => $preview['total_weight_kg'],
            'subtotal' => $preview['subtotal'],
            'name' => $product->name,
            'unit' => 'doos',
            'image_path' => $product->image_path,
            'min_quantity' => $preview['min_quantity'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cartLine
     */
    private function refreshWholeChickenCartLine(
        array &$cartLine,
        ProductGramVariant $gramVariant,
        ProductSupplier $productSupplier,
    ): void {
        $qty = (float) $cartLine['quantity'];
        $customer = $this->customer();

        $cartLine['subtotal'] = $this->pricing()->lineSubtotalForGramVariant(
            $customer,
            $productSupplier,
            $gramVariant,
            $qty,
        );
        $cartLine['ordered_pieces'] = $gramVariant->calculateOrderedPieces($qty);
        $cartLine['ordered_total_weight_kg'] = $gramVariant->calculateTotalWeightKg($qty);
        $cartLine['unit_price'] = $this->pricing()->unitPricePerBoxForGramVariant(
            $customer,
            $productSupplier,
            $gramVariant,
        );
        $cartLine['packaging_label'] = $gramVariant->boxDescription();
    }

    private function emptyPreview(): array
    {
        return [
            'is_whole_chicken' => false,
            'packaging_label' => '',
            'gram_variant_label' => '',
            'price_per_kg' => 0,
            'unit_price' => 0,
            'weight_kg' => 0,
            'total_weight_kg' => 0,
            'ordered_pieces' => null,
            'pieces_per_box' => null,
            'min_quantity' => 1,
            'subtotal' => 0,
            'supplier_name' => '',
        ];
    }

    private function cartKey(
        int $productId,
        int $productSupplierId,
        ?int $gramVariantId = null,
        ?int $packagingId = null,
    ): string {
        if ($gramVariantId) {
            return "{$productId}-gv{$gramVariantId}-ps{$productSupplierId}";
        }

        return "{$productId}-pk{$packagingId}-ps{$productSupplierId}";
    }

    private function recalculateTotal(): void
    {
        $this->total = (float) collect($this->cart)->sum('subtotal');
    }

    private function pricing(): ProductPricingService
    {
        return app(ProductPricingService::class);
    }

    private function customer(): ?\App\Models\Customer
    {
        return Auth::user()?->customer;
    }
}
?>

@php
    $ordersUrl = \App\Filament\Customer\Resources\Orders\OrderResource::getUrl('index');
    $panelUrl = \Filament\Facades\Filament::getPanel('customer')->getUrl();
@endphp

<div class="koylu-shop bg-gray-50 pb-24 lg:pb-0">

    {{-- Zoek/filterbalk: alleen sticky op mobiel, onder Filament-nav --}}
    <div class="border-b border-gray-100 bg-white shadow-sm lg:shadow-none max-lg:sticky max-lg:top-0 max-lg:z-[1]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3">
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="{{ $panelUrl }}" class="shrink-0 flex items-center lg:hidden" wire:navigate>
                    <x-brand.logo height="2.25rem" />
                </a>

                <a
                    href="{{ $ordersUrl }}"
                    wire:navigate
                    class="hidden md:inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-primary-600 transition shrink-0"
                >
                    <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                    Mijn bestellingen
                </a>

                <div class="relative flex-1 min-w-0">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="Zoek product..."
                        aria-label="Zoek producten"
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-400 focus:border-primary-400 focus:bg-white transition"
                    />
                </div>

                <div class="relative hidden sm:block shrink-0">
                    <select
                        wire:model.live="selectedSupplier"
                        aria-label="Filter op leverancier"
                        class="appearance-none bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-8 text-sm min-w-[10rem] focus:outline-none focus:ring-2 focus:ring-primary-400 focus:border-primary-400 transition"
                    >
                        <option value="">Alle leveranciers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-o-chevron-down class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                </div>

                <button
                    wire:click="toggleCart"
                    class="relative shrink-0 flex items-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-3 sm:px-4 py-2.5 rounded-xl text-sm font-semibold transition shadow-sm"
                >
                    <span wire:loading.remove wire:target="toggleCart, addToCart, placeOrder, confirmPlaceOrder">
                        <x-heroicon-o-shopping-bag class="w-4 h-4" />
                    </span>
                    <span wire:loading wire:target="toggleCart, addToCart, placeOrder, confirmPlaceOrder" class="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                    <span class="hidden sm:inline">Winkelwagen</span>
                    @if(count($cart) > 0)
                        <span class="bg-white text-primary-700 text-xs font-bold min-w-5 h-5 px-1 rounded-full flex items-center justify-center">
                            {{ count($cart) }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- Mobiel: leverancier + actieve filters --}}
            <div class="mt-3 sm:hidden space-y-2">
                <div class="relative">
                    <select
                        wire:model.live="selectedSupplier"
                        aria-label="Filter op leverancier"
                        class="w-full appearance-none bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-primary-400"
                    >
                        <option value="">Alle leveranciers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-o-chevron-down class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                </div>
            </div>

            @if($search || $selectedSupplier)
                <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-gray-100">
                    <span class="text-xs text-gray-400">Actief:</span>
                    @if($search)
                        <span class="inline-flex items-center gap-1 text-xs font-medium bg-primary-50 text-primary-700 px-2.5 py-1 rounded-full">
                            Zoek: {{ $search }}
                        </span>
                    @endif
                    @if($selectedSupplier)
                        @php $supplierName = $suppliers->firstWhere('id', $selectedSupplier)?->name; @endphp
                        <span class="inline-flex items-center gap-1 text-xs font-medium bg-koylu-green-50 text-koylu-green px-2.5 py-1 rounded-full">
                            {{ $supplierName }}
                        </span>
                    @endif
                    <button wire:click="clearFilters" type="button" class="text-xs text-gray-500 hover:text-primary-600 font-medium underline">
                        Alles wissen
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

        <div class="flex items-center justify-between mb-5">
            <p class="text-sm text-gray-500">
                <span wire:loading.remove wire:target="search, selectedSupplier, loadProducts">
                    <span class="font-semibold text-gray-800">{{ $products->count() }}</span>
                    {{ $products->count() === 1 ? 'product' : 'producten' }}
                </span>
                <span wire:loading wire:target="search, selectedSupplier, loadProducts" class="text-gray-400">Laden...</span>
            </p>
            <a href="{{ $ordersUrl }}" wire:navigate class="md:hidden text-xs text-primary-600 font-medium">Mijn bestellingen</a>
        </div>

        {{-- Loading skeleton --}}
        <div wire:loading wire:target="search, selectedSupplier, loadProducts" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @for($i = 0; $i < 8; $i++)
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden animate-pulse">
                    <div class="h-44 bg-gray-200"></div>
                    <div class="p-4 space-y-3">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-100 rounded w-full"></div>
                        <div class="h-10 bg-gray-100 rounded-xl"></div>
                    </div>
                </div>
            @endfor
        </div>

        <div wire:loading.remove wire:target="search, selectedSupplier, loadProducts">
        @if($products->isEmpty())
            <div class="text-center py-24">
                <div class="bg-primary-50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-magnifying-glass class="w-8 h-8 text-primary-300" />
                </div>
                <p class="font-semibold text-gray-700">Geen producten gevonden</p>
                <p class="text-sm text-gray-400 mt-1">Probeer een andere zoekterm of filter</p>
                @if($search || $selectedSupplier)
                    <button wire:click="clearFilters" type="button" class="mt-4 text-sm text-primary-600 font-medium hover:underline">
                        Filters wissen
                    </button>
                @endif
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($products as $product)
                    @php
                        $preview = $this->productPreview($product->id);
                        $inCart = $this->productInCart($product->id);
                        $cardQty = $this->cardQuantity($product->id);
                        $isWholeChicken = $product->isWholeChicken();
                        $hasMultiplePackagings = ! $isWholeChicken && $product->activePackagings->count() > 1;
                        $hasMultipleVariants = $isWholeChicken && $product->activeGramVariants->count() > 1;
                        $hasMultipleSuppliers = $product->activeProductSuppliers->count() > 1;
                    @endphp

                    <div @class([
                        'bg-white rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col group',
                        'border-primary-400 ring-2 ring-primary-400 ring-offset-1' => $inCart,
                        'border-gray-100 hover:border-primary-200' => ! $inCart,
                    ])>

                        <div class="h-44 bg-gray-100 relative overflow-hidden">
                            @if($product->imageUrl())
                                <img
                                    src="{{ $product->imageUrl() }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    alt="{{ $product->name }}"
                                />
                            @else
                                <div class="w-full h-full flex items-center justify-center bg-gray-50 p-6">
                                    <x-brand.logo height="3rem" class="opacity-40" />
                                </div>
                            @endif

                            @if($isWholeChicken)
                                <span class="absolute top-2 left-2 text-[10px] font-semibold uppercase tracking-wide bg-koylu-green text-white px-2 py-0.5 rounded-md shadow-sm">
                                    Hele kip
                                </span>
                            @endif

                            @if($inCart)
                                <div class="absolute top-2 right-2 bg-primary-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1 shadow">
                                    <x-heroicon-s-check class="w-3 h-3" />
                                    In wagen
                                </div>
                            @endif
                        </div>

                        <div class="p-4 flex flex-col gap-3 flex-1">

                            <h2 class="text-base font-semibold text-gray-900 leading-snug">
                                {{ $product->name }}
                            </h2>

                            @if($product->description)
                                <p class="text-sm text-gray-400 line-clamp-2">
                                    {{ $product->description }}
                                </p>
                            @endif

                            <div class="space-y-2">
                                @if($isWholeChicken)
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Gramvariant</label>
                                        <select
                                            wire:model.live="selections.{{ $product->id }}.gram_variant_id"
                                            class="mt-1 w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-primary-400"
                                        >
                                            @foreach($product->activeGramVariants as $variant)
                                                <option value="{{ $variant->id }}">{{ $variant->boxDescription() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($hasMultiplePackagings)
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Verpakking</label>
                                        <select
                                            wire:model.live="selections.{{ $product->id }}.packaging_id"
                                            class="mt-1 w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-primary-400"
                                        >
                                            @foreach($product->activePackagings as $packaging)
                                                <option value="{{ $packaging->id }}">{{ $packaging->displayLabel() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                @if($hasMultipleSuppliers)
                                    <div>
                                        <label class="text-xs font-medium text-gray-500">Leverancier</label>
                                        <select
                                            wire:model.live="selections.{{ $product->id }}.supplier_id"
                                            class="mt-1 w-full text-sm border border-gray-200 rounded-lg px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-primary-400"
                                        >
                                            @foreach($product->activeProductSuppliers as $offer)
                                                <option value="{{ $offer->id }}">{{ $offer->supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($preview['supplier_name'])
                                    <p class="text-xs text-gray-400">{{ $preview['supplier_name'] }}</p>
                                @endif
                            </div>

                            <div class="mt-auto pt-3 border-t border-gray-100 space-y-2">
                                <div>
                                    <p class="text-lg font-bold text-gray-900">
                                        €{{ number_format($preview['price_per_kg'], 2, ',', '.') }}
                                        <span class="text-sm font-normal text-gray-500">/ kg</span>
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        @if($isWholeChicken)
                                            {{ $preview['gram_variant_label'] ?? '' }}
                                            · {{ (int) ($preview['pieces_per_box'] ?? 0) }} st/doos
                                            · €{{ number_format($preview['unit_price'], 2, ',', '.') }}/doos
                                            @if($preview['min_quantity'] > 1)
                                                <span class="text-primary-400 font-medium">· min. {{ $preview['min_quantity'] + 0 }} doos</span>
                                            @endif
                                        @else
                                            {{ $preview['packaging_label'] }}
                                            · €{{ number_format($preview['unit_price'], 2, ',', '.') }} per verpakking
                                            @if($preview['min_quantity'] > 1)
                                                <span class="text-primary-400 font-medium">· min. {{ $preview['min_quantity'] + 0 }}</span>
                                            @endif
                                        @endif
                                    </p>
                                    @if($isWholeChicken)
                                        <p class="text-xs text-gray-500">
                                            1 doos = {{ number_format($preview['total_weight_kg'], 2, ',', '.') }} kg
                                            · {{ (int) ($preview['ordered_pieces'] ?? 0) }} stuks
                                        </p>
                                    @endif
                                </div>

                                {{-- Mobiel/tablet: rij; desktop (lg+ grid): gestapeld --}}
                                <div class="flex flex-row items-stretch gap-2 lg:flex-col lg:gap-2">
                                    <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden shrink-0 lg:w-full lg:justify-between">
                                        <button
                                            type="button"
                                            wire:click="decrementProductQuantity({{ $product->id }})"
                                            class="p-2.5 lg:flex-1 lg:flex lg:justify-center text-gray-500 hover:bg-gray-50 hover:text-primary-600 transition"
                                            aria-label="Minder"
                                        >
                                            <x-heroicon-o-minus class="w-4 h-4" />
                                        </button>
                                        <span class="min-w-[2.5rem] lg:min-w-0 lg:flex-1 text-center text-sm font-semibold text-gray-800 tabular-nums px-1">
                                            {{ $isWholeChicken ? (int) $cardQty : number_format($cardQty, $cardQty == (int) $cardQty ? 0 : 2, ',', '.') }}
                                        </span>
                                        <button
                                            type="button"
                                            wire:click="incrementProductQuantity({{ $product->id }})"
                                            class="p-2.5 lg:flex-1 lg:flex lg:justify-center text-gray-500 hover:bg-gray-50 hover:text-primary-600 transition"
                                            aria-label="Meer"
                                        >
                                            <x-heroicon-o-plus class="w-4 h-4" />
                                        </button>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="addToCart({{ $product->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="addToCart({{ $product->id }})"
                                        @class([
                                            'w-full flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-semibold transition',
                                            'bg-primary-100 text-primary-700 hover:bg-primary-200' => $inCart,
                                            'bg-primary-600 text-white hover:bg-primary-700 shadow-sm' => ! $inCart,
                                        ])
                                    >
                                        <span wire:loading.remove wire:target="addToCart({{ $product->id }})">
                                            <x-heroicon-o-shopping-cart class="w-4 h-4 shrink-0" />
                                        </span>
                                        <span wire:loading wire:target="addToCart({{ $product->id }})" class="w-4 h-4 border-2 border-current/30 border-t-current rounded-full animate-spin shrink-0"></span>
                                        <span>{{ $inCart ? 'Bijwerken' : 'In wagen' }}</span>
                                    </button>
                                </div>
                                @if($inCart)
                                    <p class="text-xs text-primary-600 font-medium text-center">
                                        €{{ number_format($this->productCartSubtotal($product->id), 2, ',', '.') }} in winkelwagen
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        </div>
    </div>

    {{-- Mobiele sticky winkelwagen --}}
    @if(count($cart) > 0)
        <div class="lg:hidden fixed bottom-0 inset-x-0 z-40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
            <div class="max-w-lg mx-auto bg-white border border-gray-200 rounded-2xl shadow-lg flex items-center gap-3 px-4 py-3">
                <button type="button" wire:click="toggleCart" class="flex-1 min-w-0 text-left">
                    <p class="text-xs text-gray-500">{{ count($cart) }} {{ count($cart) === 1 ? 'regel' : 'regels' }}</p>
                    <p class="text-base font-bold text-gray-900">€{{ number_format($total, 2, ',', '.') }}</p>
                </button>
                <button
                    type="button"
                    wire:click="requestPlaceOrder"
                    wire:loading.attr="disabled"
                    wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder"
                    class="shrink-0 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition"
                >
                    <span wire:loading.remove wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder">Bestellen</span>
                    <span wire:loading wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder" class="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                </button>
            </div>
        </div>
    @endif

    {{-- WINKELWAGEN DRAWER --}}
    <div
        x-data="{ open: @entangle('cartOpen') }"
        x-show="open"
        class="fixed inset-0 z-50 flex"
        x-cloak
    >
        <div
            class="fixed inset-0 bg-black/40 backdrop-blur-sm"
            x-show="open"
            x-transition.opacity
            @click="open = false"
        ></div>

        <div
            class="ml-auto w-full max-w-md bg-white shadow-2xl h-full flex flex-col relative"
            x-show="open"
            x-transition:enter="transform transition duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
        >
            <div class="flex items-center justify-between px-5 py-4 border-b bg-primary-500">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shopping-bag class="w-5 h-5 text-white" />
                    <h2 class="text-base font-semibold text-white">Winkelwagen</h2>
                    @if(count($cart) > 0)
                        <span class="bg-white/20 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'regel' : 'regels' }}
                        </span>
                    @endif
                </div>
                <button @click="open = false" class="p-1.5 rounded-lg hover:bg-white/20 transition">
                    <x-heroicon-o-x-mark class="w-5 h-5 text-white" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @forelse($cart as $cartKey => $item)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white border border-gray-100 shadow-sm">

                        @if($item['image_path'])
                            <img
                                src="{{ \App\Support\UploadStorage::url($item['image_path']) }}"
                                class="w-14 h-14 object-cover rounded-lg shrink-0"
                                alt="{{ $item['name'] }}"
                            />
                        @else
                            <div class="w-14 h-14 bg-gray-50 rounded-lg shrink-0 flex items-center justify-center p-2">
                                <x-brand.logo height="1.75rem" class="opacity-50" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-gray-900 truncate">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $item['packaging_label'] }}</p>
                            @if(!empty($item['supplier_name']))
                                <p class="text-xs text-gray-400">{{ $item['supplier_name'] }}</p>
                            @endif
                            <p class="text-xs text-gray-500 mt-0.5">
                                €{{ number_format($item['price_per_kg'], 2, ',', '.') }}/kg
                                @if($item['is_whole_chicken'] ?? false)
                                    · {{ $item['quantity'] }} doos
                                    · {{ number_format($item['ordered_pieces'] ?? 0, 0, ',', '.') }} st
                                    · {{ number_format($item['ordered_total_weight_kg'] ?? 0, 2, ',', '.') }} kg
                                @else
                                    · {{ $item['quantity'] }}× {{ number_format($item['weight_kg'], 3, ',', '.') }} kg
                                @endif
                            </p>
                            <p class="text-sm font-bold text-primary-500 mt-0.5">
                                €{{ number_format($item['subtotal'], 2, ',', '.') }}
                            </p>
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                <button
                                    type="button"
                                    wire:click="decrementCartLine('{{ $cartKey }}')"
                                    class="p-1.5 text-gray-500 hover:bg-gray-50 transition"
                                    aria-label="Minder"
                                >
                                    <x-heroicon-o-minus class="w-3.5 h-3.5" />
                                </button>
                                <span class="min-w-[2rem] text-center text-xs font-semibold tabular-nums px-1">
                                    @if($item['is_whole_chicken'] ?? false)
                                        {{ (int) $item['quantity'] }}
                                    @else
                                        {{ $item['quantity'] == (int) $item['quantity'] ? (int) $item['quantity'] : number_format($item['quantity'], 2, ',', '.') }}
                                    @endif
                                </span>
                                <button
                                    type="button"
                                    wire:click="incrementCartLine('{{ $cartKey }}')"
                                    class="p-1.5 text-gray-500 hover:bg-gray-50 transition"
                                    aria-label="Meer"
                                >
                                    <x-heroicon-o-plus class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <button
                                type="button"
                                wire:click="removeFromCart('{{ $cartKey }}')"
                                class="p-1.5 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-500 transition"
                                aria-label="Verwijderen"
                            >
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16">
                        <div class="bg-primary-50 rounded-full w-14 h-14 flex items-center justify-center mx-auto mb-3">
                            <x-heroicon-o-shopping-bag class="w-7 h-7 text-primary-300" />
                        </div>
                        <p class="font-semibold text-gray-600 text-sm">Winkelwagen is leeg</p>
                        <p class="text-xs text-gray-400 mt-1">Voeg producten toe om te bestellen</p>
                    </div>
                @endforelse
            </div>

            @if(count($cart) > 0)
                <div class="border-t bg-white p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Totaal</span>
                        <span class="text-xl font-bold text-gray-900">€{{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                    <p class="text-xs text-gray-400 -mt-1">Excl. BTW · levering op afgesproken dag</p>

                    <button
                        type="button"
                        wire:click="continueShopping"
                        class="w-full text-sm text-gray-600 hover:text-primary-600 font-medium py-2 transition"
                    >
                        Verder winkelen
                    </button>

                    <button
                        type="button"
                        wire:click="requestPlaceOrder"
                        wire:loading.attr="disabled"
                        wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder"
                        class="w-full flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 disabled:opacity-70 text-white py-3.5 rounded-xl text-sm font-bold transition shadow-sm"
                    >
                        <span wire:loading.remove wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder">
                            <x-heroicon-o-check class="w-4 h-4" />
                        </span>
                        <span wire:loading wire:target="requestPlaceOrder, confirmPlaceOrder, placeOrder" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        Bestelling plaatsen
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Bevestigingsmodal --}}
    @if($confirmOrderModalOpen)
        <div class="fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="confirm-order-title">
            <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" wire:click="cancelPlaceOrder"></div>
            <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 id="confirm-order-title" class="text-lg font-bold text-gray-900">Bestelling bevestigen</h3>
                    <p class="text-sm text-gray-500 mt-1">Controleer je winkelwagen voordat je plaatst.</p>
                </div>
                <div class="max-h-60 overflow-y-auto px-5 py-3 space-y-2">
                    @foreach($cart as $item)
                        <div class="flex justify-between gap-2 text-sm">
                            <span class="text-gray-700 truncate">{{ $item['name'] }}</span>
                            <span class="font-medium text-gray-900 shrink-0">€{{ number_format($item['subtotal'], 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 bg-gray-50 flex justify-between text-sm font-semibold">
                    <span>Totaal</span>
                    <span>€{{ number_format($total, 2, ',', '.') }}</span>
                </div>
                <div class="flex gap-3 p-4 border-t border-gray-100">
                    <button
                        type="button"
                        wire:click="cancelPlaceOrder"
                        class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition"
                    >
                        Annuleren
                    </button>
                    <button
                        type="button"
                        wire:click="confirmPlaceOrder"
                        wire:loading.attr="disabled"
                        wire:target="confirmPlaceOrder, placeOrder"
                        class="flex-1 py-3 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-bold transition disabled:opacity-70"
                    >
                        <span wire:loading.remove wire:target="confirmPlaceOrder, placeOrder">Bevestigen</span>
                        <span wire:loading wire:target="confirmPlaceOrder, placeOrder" class="inline-block w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
