<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Livewire\Component;

new class extends Component {
    public $products;
    public $cart = []; // winkelwagen: [product_id => ['quantity' => x, 'unit_price' => y]]
    public $total = 0;
    public $cartOpen = false;


    public function mount()
    {
        // Alleen actieve producten tonen
        $this->products = Product::where('is_active', true)->get();
    }

    public function toggleCart()
    {
        $this->cartOpen = !$this->cartOpen;
    }

public function addToCart($productId)
    {
        $product = $this->products->find($productId);
        if (!$product) return;

        $min = $product->min_quantity ?? 1;

        if (isset($this->cart[$productId])) {
            $this->cart[$productId]['quantity'] += $min; // voeg minimaal aantal toe
        } else {
            $this->cart[$productId] = [
                'quantity' => $min,
                'unit_price' => $product->price,
                'name' => $product->name,
                'unit' => $product->unit,
                'image_path' => $product->image_path ?? null,
                'min_quantity' => $min, // bewaren voor checks
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

        $min = $this->cart[$productId]['min_quantity'] ?? 1;

//        if ($quantity < $min) {
//            $quantity = $min; // enforce minimale afname
//            \Filament\Notifications\Notification::make()->title("Minimaal {$min} stuks vereist voor {$this->cart[$productId]['name']}")->warning()->send();
//           return;
//        }

        $this->cart[$productId]['quantity'] = $quantity;
        $this->recalculateTotal();
    }

    public function recalculateTotal()
    {
        $this->total = 0;
        foreach ($this->cart as $item) {
            $this->total += $item['quantity'] * $item['unit_price'];
        }
    }

    public function placeOrder()
    {
        if (empty($this->cart)) {
            \Filament\Notifications\Notification::make()->title('Winkel wagen is leeg')->danger()->send();
            return;
        }

        foreach ($this->cart as $productId => $item) {
            $min = $item['min_quantity'] ?? 1;

            if ($item['quantity'] < $min) {
                \Filament\Notifications\Notification::make()
                    ->title("Minimaal {$min} stuks vereist voor {$item['name']}")
                    ->warning()
                    ->send();
                return; // stop bestelling als iets te weinig is
            }
        }

        $user = Auth::user();

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(Str::random(6)),
            'customer_id' => $user->customer_id,
            'status' => 'placed',
            'order_date' => now(),
            'total_price' => $this->total,
        ]);

        foreach ($this->cart as $productId => $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $productId,
                'product_name' => $item['name'],
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        $this->cart = [];
        $this->total = 0;

        \Filament\Notifications\Notification::make()->title('Bestelling geplaatst')->success()->send();
        $this->cartOpen = !$this->cartOpen;
    }
}
?>

<div>


<div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    @foreach($products as $product)
        <div class="bg-white border rounded-lg shadow hover:shadow-lg transition p-6 flex flex-col justify-between">
            <div class="mb-4">
                @if($product->image_path)
                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="h-32 w-full object-cover rounded mb-2">
                @endif
                <h2 class="font-bold text-lg">{{ $product->name }}</h2>
                <p class="text-gray-600 mt-1 text-sm">{{ $product->description }}</p>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <span class="font-semibold">€{{ number_format($product->price,2) }} / {{ $product->unit }}</span>
                <button wire:click="addToCart({{ $product->id }})"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition"
                        @if(isset($cart[$product->id])) disabled @endif>
                    @if(isset($cart[$product->id])) Toegevoegd @else + Voeg toe @endif
                </button>
            </div>
        </div>
    @endforeach
</div>

{{-- Knop om winkelwagen modal te openen --}}
<button wire:click="toggleCart"
        class="fixed bottom-6 right-6 bg-green-600 text-white px-6 py-3 rounded-full shadow-lg hover:bg-green-700 transition z-50">
    Winkelwagen ({{ count($cart) }})
</button>

{{-- Flyout Modal --}}
<div x-data="{ open: @entangle('cartOpen') }" x-show="open" class="fixed inset-0 z-40 flex">
    <div class="fixed inset-0 bg-black/50 z-30" x-show="open" x-transition.opacity @click="open = false"></div>
    <div class="ml-auto w-full max-w-lg bg-white shadow-xl h-full p-6 z-40"
         x-show="open"
         x-transition:enter="transform transition-transform duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition-transform duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        <button @click="open = false" class="absolute top-4 right-4 text-gray-500 hover:text-gray-800">&times;</button>

        <h2 class="font-bold text-2xl mb-4">Winkelwagen</h2>

        @if($cart)
            <div class="overflow-y-auto max-h-[70vh] space-y-4">
                @foreach($cart as $productId => $item)
                    <div class="flex items-center border-b pb-2">
                        @if($item['image_path'])
                            <img src="{{ asset('storage/' . $item['image_path']) }}" class="h-16 w-16 object-cover rounded mr-3">
                        @endif
                        <div class="flex-1">
                            <p class="font-semibold">{{ $item['name'] }}</p>
                            <p class="text-sm text-gray-600">€{{ number_format($item['unit_price'], 2) }} / {{ $item['unit'] }}</p>
                        </div>
                            <input type="number" min="1"
                                   wire:change="updateQuantity({{ $item['product_id'] ?? $productId }}, $event.target.value)"
                                   value="{{ $item['quantity'] }}"
                                   class="border px-2 py-1 w-20 rounded mr-3">

                            <button wire:click="removeFromCart({{ $item['product_id'] ?? $productId }})"
                                    class="text-red-600 hover:underline">Verwijder</button>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex justify-between items-center">
                <span class="font-bold text-xl">Totaal: €{{ number_format($total, 2) }}</span>
                <button wire:click="placeOrder" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">Bestel</button>
            </div>
        @else
            <p class="text-gray-500">Je winkelwagen is leeg.</p>
        @endif
    </div>
</div>
</div>
