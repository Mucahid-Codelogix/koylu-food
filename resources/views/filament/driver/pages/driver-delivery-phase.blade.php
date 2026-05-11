<x-filament-panels::page>
    @php
        $stop        = $this->getCurrentStop();
        $total       = $this->getTotalStops();
        $current     = $this->currentStopIndex + 1;
        $isLast      = $current === $total;
        $isDelivered = $stop->status === 'delivered';
        $customer    = $stop->order->customer;
    @endphp

    {{-- Progress header --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-4 space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-4 h-4 text-blue-500" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Stop {{ $current }} van {{ $total }}
                </p>
            </div>
            <span class="text-xs font-semibold text-blue-600 bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1 rounded-full">
                Leveringsfase
            </span>
        </div>
        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
            <div
                class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                style="width: {{ ($current / $total) * 100 }}%"
            ></div>
        </div>
    </div>

    {{-- Klant info --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="bg-blue-500 px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 rounded-xl p-2">
                    <x-heroicon-o-building-storefront class="w-5 h-5 text-white" />
                </div>
                <div>
                    <p class="text-blue-100 text-xs font-medium">Stop {{ $stop->stop_order }}</p>
                    <p class="text-white text-lg font-bold leading-tight">{{ $customer->company_name }}</p>
                </div>
            </div>
            @if ($isDelivered)
                <span class="bg-white text-blue-600 text-xs font-semibold px-3 py-1 rounded-full flex items-center gap-1">
                    <x-heroicon-s-check class="w-3 h-3" />
                    Geleverd
                </span>
            @endif
        </div>

        <div class="px-5 py-4 space-y-2">
            <div class="flex items-start gap-2 text-sm text-gray-500">
                <x-heroicon-o-map-pin class="w-4 h-4 shrink-0 mt-0.5 text-gray-400" />
                <span>{{ $customer->address }}, {{ $customer->postal_code }} {{ $customer->city }}</span>
            </div>
            @if ($customer->phone)
                <a href="tel:{{ $customer->phone }}" class="flex items-center gap-2 text-sm text-blue-500 hover:text-blue-600 transition">
                    <x-heroicon-o-phone class="w-4 h-4 shrink-0" />
                    {{ $customer->phone }}
                </a>
            @endif
        </div>
    </div>

    {{-- Producten (US-C3, C4, C5) --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
            <x-heroicon-o-shopping-bag class="w-4 h-4 text-gray-400" />
            <h3 class="font-semibold text-sm text-gray-700 dark:text-white">Producten</h3>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($deliveryData as $orderItemId => $item)
                @if (!isset($item['product_name'], $item['is_missed'])) @continue @endif

                <div @class([
                    'p-4 space-y-3 transition',
                    'bg-red-50 dark:bg-red-900/10' => $item['is_missed'],
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-800 dark:text-white truncate">{{ $item['product_name'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Besteld: {{ $item['ordered_quantity'] }} {{ $item['unit'] }}
                            </p>
                        </div>

                        {{-- US-C5: Gemist toggle --}}
                        <label class="flex items-center gap-2 cursor-pointer shrink-0">
                            <span class="text-xs font-medium text-red-500">Gemist</span>
                            <input
                                type="checkbox"
                                wire:model.live="deliveryData.{{ $orderItemId }}.is_missed"
                                @disabled($isDelivered)
                                class="rounded border-gray-300 text-red-500 disabled:opacity-50"
                            />
                        </label>
                    </div>

                    @if (!$item['is_missed'])
                        {{-- US-C4: Geleverd aantal --}}
                        <div>
                            <label class="text-xs font-medium text-gray-500">Geleverd aantal</label>
                            <input
                                type="number"
                                wire:model.live="deliveryData.{{ $orderItemId }}.delivered_quantity"
                                @disabled($isDelivered)
                                min="0"
                                step="1"
                                class="mt-1 w-full p-2 rounded-xl border-gray-200 text-sm shadow-sm focus:border-blue-400 focus:ring-blue-400 disabled:bg-gray-50 disabled:text-gray-400"
                            />
                            @if ($item['delivered_quantity'] != $item['ordered_quantity'])
                                <div class="flex items-center gap-1.5 mt-1.5">
                                    <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                    <p class="text-xs text-amber-600">
                                        Afwijking: {{ $item['ordered_quantity'] - $item['delivered_quantity'] }} {{ $item['unit'] }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- Reden gemist --}}
                        <div>
                            <label class="text-xs font-medium text-gray-500">Reden</label>
                            <input
                                type="text"
                                wire:model.live="deliveryData.{{ $orderItemId }}.missed_reason"
                                @disabled($isDelivered)
                                placeholder="bijv. niet op voorraad"
                                class="mt-1   p-2  w-full rounded-xl border-red-200 text-sm shadow-sm focus:border-red-400 focus:ring-red-400 disabled:bg-gray-50 disabled:text-gray-400"
                            />
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- US-C6: Kratten --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
            <x-heroicon-o-archive-box class="w-4 h-4 text-gray-400" />
            <h3 class="font-semibold text-sm text-gray-700 dark:text-white">Kratten</h3>
        </div>
        <div class="p-5 grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs font-medium text-gray-500">Meegegeven</label>
                <input
                    type="number"
                    wire:model.live="cratesGiven"
                    @disabled($isDelivered)
                    min="0"
                    class="mt-1 w-full  p-2  rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400"
                />
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500">Retour ontvangen</label>
                <input
                    type="number"
                    wire:model.live="cratesReturned"
                    @disabled($isDelivered)
                    min="0"
                    class="mt-1 w-full  p-2  rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400"
                />
            </div>
        </div>
    </div>

    {{-- US-C7: Handtekening --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
            <x-heroicon-o-pencil class="w-4 h-4 text-gray-400" />
            <h3 class="font-semibold text-sm text-gray-700 dark:text-white">Handtekening</h3>
        </div>

        <div class="p-5 space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-500">Naam ontvanger</label>
                <input
                    type="text"
                    wire:model.live="receiverName"
                    @disabled($isDelivered)
                    placeholder="Volledige naam"
                    class="mt-1 w-full  p-2  rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400"
                />
            </div>

            @if ($isDelivered)
                @php
                    $existingDelivery = \App\Models\Delivery::where('order_id', $stop->order_id)->first();
                @endphp
                @if ($existingDelivery?->signature_path)
                    <div>
                        <label class="text-xs font-medium text-gray-500 block mb-2">Geregistreerde handtekening</label>
                        <div class="border border-gray-200 rounded-xl bg-gray-50 p-2">
                            <img
                                src="{{ \Storage::disk('public')->url($existingDelivery->signature_path) }}"
                                alt="Handtekening"
                                class="w-full object-contain max-h-36 rounded-lg"
                            />
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-gray-400 text-sm">
                        <x-heroicon-o-exclamation-circle class="w-4 h-4" />
                        Geen handtekening gevonden
                    </div>
                @endif
            @else
                <div>
                    <label class="text-xs font-medium text-gray-500 block mb-2">Teken hieronder</label>
                    <canvas
                        id="signature-pad"
                        class="w-full border-2 border-dashed border-gray-200 rounded-xl bg-gray-50 touch-none"
                        height="160"
                    ></canvas>
                    <div class="flex items-center justify-between mt-2">
                        <button
                            type="button"
                            onclick="clearSignature()"
                            class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition"
                        >
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
                            Opnieuw tekenen
                        </button>
                        @if ($signature)
                            <span class="flex items-center gap-1.5 text-xs text-green-500 font-medium">
                                <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                Handtekening opgeslagen
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Actie knoppen --}}
    <div class="pb-8 space-y-2">
        @if ($isDelivered)
            <div class="w-full flex items-center justify-center gap-2 py-4 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-400 font-semibold">
                <x-heroicon-s-check-circle class="w-5 h-5 text-green-500" />
                Al geleverd
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @if ($this->currentStopIndex > 0)
                    <button
                        wire:click="previousStop"
                        class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium hover:bg-gray-50 transition"
                    >
                        <x-heroicon-o-arrow-left class="w-4 h-4" />
                        Vorige stop
                    </button>
                @endif
                @if (!$isLast)
                    <button
                        wire:click="nextStop"
                        class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-semibold transition"
                    >
                        Volgende stop
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </button>
                @endif
            </div>

        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @if ($this->currentStopIndex > 0)
                    <button
                        wire:click="previousStop"
                        class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium hover:bg-gray-50 transition"
                    >
                        <x-heroicon-o-arrow-left class="w-4 h-4" />
                        Vorige stop
                    </button>
                @endif
                <button
                    wire:click="skipStop"
                    class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-gray-200 text-gray-500 text-sm font-medium hover:bg-gray-50 transition"
                >
                    Overslaan
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </button>
            </div>

            <button
                wire:click="saveDelivery"
                class="w-full flex items-center justify-center gap-2 py-4 rounded-xl bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold text-lg transition"
            >
                <x-heroicon-o-check-circle class="w-5 h-5" />
                {{ $isLast ? 'Route afronden' : 'Levering bevestigen' }}
            </button>
        @endif
    </div>

    {{-- Signature Pad JS --}}
    @push('scripts')
        <script>
            document.addEventListener('livewire:initialized', () => {
                Livewire.on('clear-signature', () => clearSignature());
                Livewire.on('stop-changed', () => {
                    clearSignature();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });

            const canvas = document.getElementById('signature-pad');
            const ctx = canvas.getContext('2d');
            let drawing = false;

            canvas.width = canvas.offsetWidth;

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const src = e.touches ? e.touches[0] : e;
                return { x: src.clientX - rect.left, y: src.clientY - rect.top };
            }

            canvas.addEventListener('mousedown',  e => { drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); });
            canvas.addEventListener('mousemove',  e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); });
            canvas.addEventListener('mouseup',    () => { drawing = false; saveSignature(); });
            canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; ctx.beginPath(); const p = getPos(e); ctx.moveTo(p.x, p.y); }, { passive: false });
            canvas.addEventListener('touchmove',  e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }, { passive: false });
            canvas.addEventListener('touchend',   () => { drawing = false; saveSignature(); });

            function saveSignature() {
                const data = canvas.toDataURL('image/png');
                @this.set('signature', data);
            }

            function clearSignature() {
                if (!canvas) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                @this.set('signature', '');
            }
        </script>
    @endpush
</x-filament-panels::page>
