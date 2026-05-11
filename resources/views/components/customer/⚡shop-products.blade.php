<?php

use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Livewire\Component;
use Filament\Notifications\Notification;

new class extends Component {
    public $products;
    public $cart = [];
    public $total = 0;
    public $cartOpen = false;
    public $suppliers;
    public $selectedSupplier = null;
    public $search = '';

    public function mount()
    {
        $this->suppliers = Supplier::where('is_active', true)->get();
        $this->loadProducts();
    }

    public function loadProducts()
    {
        $this->products = Product::query()
            ->where('is_active', true)
            ->when($this->selectedSupplier, fn($q) => $q->where('supplier_id', $this->selectedSupplier))
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->with('supplier')
            ->get();
    }

    public function updatedSelectedSupplier() { $this->loadProducts(); }
    public function updatedSearch() { $this->loadProducts(); }
    public function toggleCart() { $this->cartOpen = !$this->cartOpen; }

    public function addToCart($productId)
    {
        $product = $this->products->find($productId);
        if (!$product) return;

        $min = $product->min_quantity ?? 1;

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $min;
        } else {
            $this->cart[$productId] = [
                'product_id'    => $product->id,
                'supplier_name' => $product->supplier?->name,
                'quantity'      => $min,
                'unit_price'    => $product->price,
                'name'          => $product->name,
                'unit'          => $product->unit,
                'image_path'    => $product->image_path ?? null,
                'min_quantity'  => $min,
            ];
        }

        $this->recalculateTotal();
    }

    public function removeFromCart($productId)
    {
        unset($this->cart[$productId]);
        $this->recalculateTotal();
    }

    public function updateQuantity($productId, $quantity)
    {
        if (!isset($this->cart[$productId])) return;
        $this->cart[$productId]['quantity'] = max(1, (int) $quantity);
        $this->recalculateTotal();
    }

    public function recalculateTotal()
    {
        $this->total = collect($this->cart)->sum(fn($i) => $i['quantity'] * $i['unit_price']);
    }

    public function placeOrder()
    {
        if (empty($this->cart)) {
            Notification::make()->title('Winkelwagen is leeg')->danger()->send();
            return;
        }

        foreach ($this->cart as $item) {
            if ($item['quantity'] < ($item['min_quantity'] ?? 1)) {
                Notification::make()->title("Minimaal {$item['min_quantity']} stuks vereist voor {$item['name']}")->warning()->send();
                return;
            }
        }

        $user = Auth::user();
        DB::beginTransaction();

        try {
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(6)),
                'customer_id'  => $user->customer_id,
                'status'       => 'placed',
                'order_date'   => now(),
                'total_price'  => $this->total,
            ]);

            foreach ($this->cart as $productId => $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $productId,
                    'product_name' => $item['name'],
                    'unit'         => $item['unit'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $item['quantity'] * $item['unit_price'],
                ]);
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

        Notification::make()->title('Bestelling geplaatst!')->success()->send();
    }
}
?>

<div class="min-h-screen bg-gray-50">

    {{-- STICKY HEADER --}}
    <div class="sticky top-0 z-30 bg-white border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
            <div class="flex items-center gap-3">

                {{-- Zoekbalk --}}
                <div class="relative flex-1">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Zoek product..."
                        class="w-full pl-9 pr-4 py-2.5 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 focus:bg-white transition"
                    />
                </div>

                {{-- Leverancier filter --}}
                <div class="relative hidden sm:block">
                    <select
                        wire:model.live="selectedSupplier"
                        class="appearance-none bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400 transition"
                    >
                        <option value="">Alle leveranciers</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-o-chevron-down class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                </div>

                @if($selectedSupplier)
                    <button wire:click="$set('selectedSupplier', null)" class="flex items-center gap-1 text-sm text-gray-400 hover:text-orange-500 transition whitespace-nowrap">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                        <span class="hidden sm:inline">Wissen</span>
                    </button>
                @endif

                {{-- Cart knop --}}
                <button
                    wire:click="toggleCart"
                    class="relative flex items-center gap-2 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white px-4 py-2.5 rounded-xl text-sm font-semibold transition shadow-sm shadow-orange-200"
                >
                    <x-heroicon-o-shopping-bag class="w-4 h-4" />
                    <span class="hidden sm:inline">Winkelwagen</span>
                    @if(count($cart) > 0)
                        <span class="bg-white text-orange-600 text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">
                            {{ count($cart) }}
                        </span>
                    @endif
                </button>

            </div>
        </div>
    </div>

    {{-- PRODUCT GRID --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

        {{-- Resultaten info --}}
        <div class="flex items-center justify-between mb-5">
            <p class="text-sm text-gray-500">
                <span class="font-semibold text-gray-800">{{ $products->count() }}</span>
                {{ $products->count() === 1 ? 'product' : 'producten' }} gevonden
            </p>
            @if($search || $selectedSupplier)
                <button
                    wire:click="$set('search', ''); $set('selectedSupplier', null)"
                    class="text-xs text-orange-500 hover:text-orange-700 flex items-center gap-1 transition font-medium"
                >
                    <x-heroicon-o-x-mark class="w-3.5 h-3.5" />
                    Filters wissen
                </button>
            @endif
        </div>

        @if($products->isEmpty())
            <div class="text-center py-24">
                <div class="bg-orange-50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-magnifying-glass class="w-8 h-8 text-orange-300" />
                </div>
                <p class="font-semibold text-gray-700">Geen producten gevonden</p>
                <p class="text-sm text-gray-400 mt-1">Probeer een andere zoekterm of filter</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @foreach($products as $product)
                    @php $inCart = isset($cart[$product->id]); @endphp

                    <div @class([
                        'bg-white rounded-2xl border shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col group',
                        'border-orange-400 ring-2 ring-orange-400 ring-offset-1' => $inCart,
                        'border-gray-100 hover:border-orange-200'                => !$inCart,
                    ])>

                        {{-- Afbeelding --}}
                        <div class="h-44 bg-gray-100 relative overflow-hidden">
                            @if($product->image_path)
                                <img
                                    src="{{ asset('storage/' . $product->image_path) }}"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    alt="{{ $product->name }}"
                                />
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-300">
                                    <x-heroicon-o-photo class="w-10 h-10" />
                                </div>
                            @endif

                            {{-- In wagen badge --}}
                            @if($inCart)
                                <div class="absolute top-2 right-2 bg-orange-500 text-white text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1 shadow">
                                    <x-heroicon-s-check class="w-3 h-3" />
                                    In wagen
                                </div>
                            @endif

                            {{-- Leverancier badge bovenlinks --}}
                            @if($product->supplier)
                                <div class="absolute top-2 left-2 bg-white/90 backdrop-blur-sm text-gray-600 text-xs font-medium px-2 py-0.5 rounded-full">
                                    {{ $product->supplier->name }}
                                </div>
                            @endif
                        </div>

                        {{-- Content --}}
                        <div class="p-4 flex flex-col gap-2 flex-1">

                            <h2 class="text-base font-semibold text-gray-900 leading-snug">
                                {{ $product->name }}
                            </h2>

                            @if($product->description)
                                <p class="text-sm text-gray-400 line-clamp-2 flex-1">
                                    {{ $product->description }}
                                </p>
                            @endif

                            {{-- Prijs + knop --}}
                            <div class="mt-auto pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                                <div>
                                    <p class="text-lg font-bold text-gray-900">
                                        €{{ number_format($product->price, 2) }}
                                    </p>
                                    <p class="text-xs text-gray-400">
                                        per {{ $product->unit }}
                                        @if($product->min_quantity > 1)
                                            <span class="text-orange-400 font-medium">· min. {{ $product->min_quantity }}</span>
                                        @endif
                                    </p>
                                </div>

                                <button
                                    wire:click="addToCart({{ $product->id }})"
                                    @class([
                                        'flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold transition',
                                        'bg-orange-100 text-orange-600 hover:bg-orange-200' => $inCart,
                                        'bg-orange-500 text-white hover:bg-orange-600 shadow-sm shadow-orange-200' => !$inCart,
                                    ])
                                >
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                    {{ $inCart ? 'Meer' : 'Toevoegen' }}
                                </button>
                            </div>

                            {{-- Aantal in wagen indicator --}}
                            @if($inCart)
                                <div class="flex items-center gap-1.5 text-xs text-orange-600 font-medium bg-orange-50 rounded-lg px-2.5 py-1.5">
                                    <x-heroicon-o-shopping-bag class="w-3.5 h-3.5" />
                                    {{ $cart[$product->id]['quantity'] }} {{ $product->unit }} in winkelwagen
                                    · €{{ number_format($cart[$product->id]['quantity'] * $product->price, 2) }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- WINKELWAGEN DRAWER --}}
    <div
        x-data="{ open: @entangle('cartOpen') }"
        x-show="open"
        class="fixed inset-0 z-50 flex"
        x-cloak
    >
        {{-- Overlay --}}
        <div
            class="fixed inset-0 bg-black/40 backdrop-blur-sm"
            x-show="open"
            x-transition:enter="transition duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="open = false"
        ></div>

        {{-- Panel --}}
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
            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b bg-orange-500">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shopping-bag class="w-5 h-5 text-white" />
                    <h2 class="text-base font-semibold text-white">Winkelwagen</h2>
                    @if(count($cart) > 0)
                        <span class="bg-white/20 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                            {{ count($cart) }} {{ count($cart) === 1 ? 'item' : 'items' }}
                        </span>
                    @endif
                </div>
                <button @click="open = false" class="p-1.5 rounded-lg hover:bg-white/20 transition">
                    <x-heroicon-o-x-mark class="w-5 h-5 text-white" />
                </button>
            </div>

            {{-- Items --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @forelse($cart as $productId => $item)
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-white border border-gray-100 shadow-sm">

                        @if($item['image_path'])
                            <img
                                src="{{ asset('storage/' . $item['image_path']) }}"
                                class="w-14 h-14 object-cover rounded-lg shrink-0"
                                alt="{{ $item['name'] }}"
                            />
                        @else
                            <div class="w-14 h-14 bg-orange-50 rounded-lg shrink-0 flex items-center justify-center">
                                <x-heroicon-o-photo class="w-6 h-6 text-orange-300" />
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-gray-900 truncate">{{ $item['name'] }}</p>
                            @if(!empty($item['supplier_name']))
                                <p class="text-xs text-gray-400">{{ $item['supplier_name'] }}</p>
                            @endif
                            <p class="text-sm font-bold text-orange-500 mt-0.5">
                                €{{ number_format($item['unit_price'] * $item['quantity'], 2) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <input
                                type="number"
                                min="1"
                                wire:change="updateQuantity({{ $productId }}, $event.target.value)"
                                value="{{ $item['quantity'] }}"
                                class="w-14 text-center border border-gray-200 rounded-lg py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-400 focus:border-orange-400"
                            />
                            <button
                                wire:click="removeFromCart({{ $productId }})"
                                class="p-1.5 rounded-lg hover:bg-red-50 text-gray-300 hover:text-red-500 transition"
                            >
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16">
                        <div class="bg-orange-50 rounded-full w-14 h-14 flex items-center justify-center mx-auto mb-3">
                            <x-heroicon-o-shopping-bag class="w-7 h-7 text-orange-300" />
                        </div>
                        <p class="font-semibold text-gray-600 text-sm">Winkelwagen is leeg</p>
                        <p class="text-xs text-gray-400 mt-1">Voeg producten toe om te bestellen</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            @if(count($cart) > 0)
                <div class="border-t bg-white p-4 space-y-3">

                    {{-- Overzicht --}}
                    <div class="bg-orange-50 rounded-xl p-3 space-y-1.5">
                        @foreach($cart as $item)
                            <div class="flex items-center justify-between text-xs text-gray-600">
                                <span class="truncate flex-1 mr-2">{{ $item['name'] }}</span>
                                <span class="shrink-0 font-medium">{{ $item['quantity'] }}x · €{{ number_format($item['unit_price'] * $item['quantity'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <span class="text-sm text-gray-500">Totaal</span>
                        <span class="text-xl font-bold text-gray-900">€{{ number_format($total, 2) }}</span>
                    </div>
                    <p class="text-xs text-gray-400">Excl. BTW · levering op afgesproken dag</p>

                    <button
                        wire:click="placeOrder"
                        class="w-full flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white py-3.5 rounded-xl text-sm font-bold transition shadow-sm shadow-orange-200"
                    >
                        <x-heroicon-o-check class="w-4 h-4" />
                        Bestelling plaatsen
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
