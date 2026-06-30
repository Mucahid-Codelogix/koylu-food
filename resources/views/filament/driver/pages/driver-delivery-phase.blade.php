<x-filament-panels::page>
    @php
        $stop        = $this->getCurrentStop();
        $total       = $this->getTotalStops();
        $current     = $this->currentStopIndex + 1;
        $isLast      = $current === $total;
        $isDelivered = $stop->status === \App\Enums\RouteStopStatus::DELIVERED;
        $isSkipped   = $stop->status === \App\Enums\RouteStopStatus::SKIPPED;
        $isHandled   = $isDelivered || $isSkipped;
        $customer    = $stop->order->customer;
    @endphp

    <div class="koylu-page">
        <x-koylu.phase-progress
            :current="$current"
            :total="$total"
            :label="'Stop ' . $current . ' van ' . $total"
            phase="Leveringsfase"
            icon="heroicon-o-map-pin"
        />

        <x-koylu.card>
            <div class="koylu-card-hero">
                <div class="flex items-center gap-3">
                    <div class="koylu-card-hero-icon">
                        <x-heroicon-o-building-storefront class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <p class="koylu-card-hero-label">Stop {{ $stop->stop_order }}</p>
                        <p class="text-white text-lg font-bold leading-tight">{{ $customer->company_name }}</p>
                    </div>
                </div>
                @if ($isDelivered)
                    <x-koylu.badge variant="success">
                        <x-heroicon-s-check class="w-3 h-3" />
                        Geleverd
                    </x-koylu.badge>
                @elseif ($isSkipped)
                    <x-koylu.badge variant="warning">
                        Overgeslagen
                    </x-koylu.badge>
                @endif
            </div>

            <div class="koylu-card-body space-y-2">
                <div class="flex items-start gap-2 text-sm text-gray-500">
                    <x-heroicon-o-map-pin class="w-4 h-4 shrink-0 mt-0.5 text-gray-400" />
                    <span>{{ $customer->address }}, {{ $customer->postal_code }} {{ $customer->city }}</span>
                </div>
                @if ($customer->phone)
                    <a href="tel:{{ $customer->phone }}" class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 transition">
                        <x-heroicon-o-phone class="w-4 h-4 shrink-0" />
                        {{ $customer->phone }}
                    </a>
                @endif
            </div>
        </x-koylu.card>

        <x-koylu.card>
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shopping-bag class="w-4 h-4 text-gray-400" />
                    <x-koylu.section-heading title="Producten" />
                </div>
            </x-slot:header>

            <div class="divide-y divide-gray-50 dark:divide-gray-800/80">
                @foreach ($deliveryData as $orderItemId => $item)
                    @if (! isset($item['product_name'], $item['is_missed']))
                        @continue
                    @endif

                    <div @class([
                        'p-4 space-y-3 transition',
                        'bg-red-50 dark:bg-red-900/10' => $item['is_missed'],
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 dark:text-white truncate">{{ $item['product_name'] }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Besteld: {{ $item['ordered_quantity'] }} {{ $item['unit'] }}
                                    @if ($item['actual_weight_kg'] !== null)
                                        · <span class="font-semibold text-gray-600 dark:text-gray-300">{{ number_format((float) $item['actual_weight_kg'], 3, ',', '.') }} kg</span>
                                    @endif
                                </p>
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer shrink-0">
                                <span class="text-xs font-medium text-red-500">Gemist</span>
                                <input
                                    type="checkbox"
                                    wire:model.live="deliveryData.{{ $orderItemId }}.is_missed"
                                    @disabled($isHandled)
                                    class="rounded border-gray-300 text-red-500 disabled:opacity-50"
                                />
                            </label>
                        </div>

                        @if (! $item['is_missed'])
                            <div>
                                <label class="text-xs font-medium text-gray-500">Geleverd aantal</label>
                                <input
                                    type="number"
                                    wire:model.live="deliveryData.{{ $orderItemId }}.delivered_quantity"
                                    @disabled($isHandled)
                                    min="0"
                                    step="1"
                                    class="mt-1 w-full p-2 rounded-xl border-gray-200 text-sm shadow-sm focus:border-primary-400 focus:ring-primary-400 disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900 dark:border-gray-700"
                                />
                                @if ($item['delivered_quantity'] != $item['ordered_quantity'])
                                    <div class="flex items-center gap-1.5 mt-1.5">
                                        <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                        <p class="text-xs text-amber-600">
                                            Afwijking: {{ $item['ordered_quantity'] - $item['delivered_quantity'] }} {{ $item['unit'] }}
                                        </p>
                                    </div>
                                    <div class="mt-2">
                                        <label class="text-xs font-medium text-gray-500">Retour-notitie (optioneel)</label>
                                        <input
                                            type="text"
                                            wire:model.live="deliveryData.{{ $orderItemId }}.return_note"
                                            @disabled($isHandled)
                                            placeholder="bijv. 2 colli retour, beschadigd"
                                            class="mt-1 p-2 w-full rounded-xl border-amber-200 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900"
                                        />
                                    </div>
                                @endif
                            </div>
                        @else
                            <div>
                                <label class="text-xs font-medium text-gray-500">Reden</label>
                                <input
                                    type="text"
                                    wire:model.live="deliveryData.{{ $orderItemId }}.missed_reason"
                                    @disabled($isHandled)
                                    placeholder="bijv. niet op voorraad"
                                    class="mt-1 p-2 w-full rounded-xl border-red-200 text-sm shadow-sm focus:border-red-400 focus:ring-red-400 disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900"
                                />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-koylu.card>

        <x-koylu.card>
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-archive-box class="w-4 h-4 text-gray-400" />
                    <x-koylu.section-heading title="Kratten" />
                </div>
            </x-slot:header>

            <div class="koylu-card-body grid grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-500">Meegegeven</label>
                    <input
                        type="number"
                        wire:model.live="cratesGiven"
                        @disabled($isHandled)
                        min="0"
                        class="mt-1 w-full p-2 rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900 dark:border-gray-700"
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500">Retour ontvangen</label>
                    <input
                        type="number"
                        wire:model.live="cratesReturned"
                        @disabled($isHandled)
                        min="0"
                        class="mt-1 w-full p-2 rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900 dark:border-gray-700"
                    />
                </div>
            </div>
        </x-koylu.card>

        <x-koylu.card>
            <x-slot:header>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-pencil class="w-4 h-4 text-gray-400" />
                    <x-koylu.section-heading title="Handtekening" />
                </div>
            </x-slot:header>

            <div class="koylu-card-body space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-500">Naam ontvanger</label>
                    <input
                        type="text"
                        wire:model.live="receiverName"
                        @disabled($isHandled)
                        placeholder="Volledige naam"
                        class="mt-1 w-full p-2 rounded-xl border-gray-200 text-sm shadow-sm disabled:bg-gray-50 disabled:text-gray-400 dark:bg-gray-900 dark:border-gray-700"
                    />
                </div>

                @if ($isHandled)
                    @php
                        $existingDelivery = \App\Models\Delivery::where('order_id', $stop->order_id)->first();
                    @endphp
                    @if ($existingDelivery?->signature_path)
                        <div>
                            <label class="text-xs font-medium text-gray-500 block mb-2">Geregistreerde handtekening</label>
                            <div class="border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-800/50 p-2">
                                <img
                                    src="{{ \App\Support\UploadStorage::url($existingDelivery->signature_path) }}"
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
                        <div wire:ignore>
                            <canvas
                                id="signature-pad"
                                class="block w-full h-40 max-w-full border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 touch-none"
                            ></canvas>
                        </div>
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
                                <span class="flex items-center gap-1.5 text-xs text-koylu-green font-medium">
                                    <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                                    Handtekening opgeslagen
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </x-koylu.card>

        <div class="pb-8 space-y-2">
            @if ($isHandled)
                <div class="w-full flex items-center justify-center gap-2 py-4 rounded-xl bg-gray-100 dark:bg-gray-800 text-gray-400 font-semibold">
                    @if ($isDelivered)
                        <x-heroicon-s-check-circle class="w-5 h-5 text-koylu-green" />
                        Al geleverd
                    @else
                        <x-heroicon-o-forward class="w-5 h-5 text-amber-500" />
                        Stop overgeslagen
                    @endif
                </div>

                @if ($isSkipped)
                    <x-koylu.alert variant="warning" class="mb-0">
                        <p class="text-sm">Per ongeluk overgeslagen? Je kunt deze stop alsnog afleveren.</p>
                    </x-koylu.alert>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @if ($this->currentStopIndex > 0)
                        <button wire:click="previousStop" class="koylu-btn-secondary w-full">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            Vorige stop
                        </button>
                    @endif
                    @if (! $isLast)
                        <button wire:click="nextStop" class="koylu-btn-secondary w-full {{ $this->currentStopIndex === 0 ? 'sm:col-span-2' : '' }}">
                            Volgende stop
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </button>
                    @endif
                </div>

                @if ($isSkipped)
                    <button wire:click="resumeStop" class="koylu-btn-primary py-4 text-lg">
                        <x-heroicon-o-arrow-path class="w-5 h-5" />
                        Levering hervatten
                    </button>
                @endif

            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @if ($this->currentStopIndex > 0)
                        <button wire:click="previousStop" class="koylu-btn-secondary w-full">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            Vorige stop
                        </button>
                    @endif
                    @if (! $isLast)
                        <button wire:click="nextStop" class="koylu-btn-secondary w-full">
                            Volgende stop
                            <x-heroicon-o-arrow-right class="w-4 h-4" />
                        </button>
                    @endif
                    <div @class([
                        'w-full',
                        'sm:col-span-2' => $this->currentStopIndex === 0 && $isLast,
                    ])>
                        {{ $this->skipStopAction }}
                    </div>
                </div>

                <div class="w-full">
                    {{ $this->saveDeliveryAction }}
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                let pad = null;

                function createPad() {
                    const canvas = document.getElementById('signature-pad');
                    if (!canvas) {
                        pad?.unbind?.();
                        pad = null;
                        return null;
                    }

                    if (pad?.canvas === canvas) {
                        return pad;
                    }

                    pad?.unbind?.();

                    pad = {
                        canvas,
                        ctx: canvas.getContext('2d'),
                        drawing: false,
                        hasInk: false,
                        resize() {
                            const rect = this.canvas.getBoundingClientRect();
                            const dpr = window.devicePixelRatio || 1;
                            const width = Math.max(Math.floor(rect.width), 1);
                            const height = Math.max(Math.floor(rect.height), 1);
                            const pixelWidth = Math.floor(width * dpr);
                            const pixelHeight = Math.floor(height * dpr);

                            if (this.canvas.width === pixelWidth && this.canvas.height === pixelHeight) {
                                return;
                            }

                            const snapshot = this.hasInk ? this.canvas.toDataURL('image/png') : null;

                            this.canvas.width = pixelWidth;
                            this.canvas.height = pixelHeight;
                            this.ctx.setTransform(1, 0, 0, 1, 0, 0);
                            this.ctx.scale(dpr, dpr);
                            this.ctx.lineCap = 'round';
                            this.ctx.lineJoin = 'round';
                            this.ctx.lineWidth = 2.5;
                            this.ctx.strokeStyle = '#1a1a1a';

                            if (snapshot) {
                                const img = new Image();
                                img.onload = () => {
                                    this.ctx.drawImage(img, 0, 0, width, height);
                                };
                                img.src = snapshot;
                            }
                        },
                        getPos(e) {
                            const rect = this.canvas.getBoundingClientRect();
                            const src = e.touches?.length
                                ? e.touches[0]
                                : (e.changedTouches?.length ? e.changedTouches[0] : e);

                            return {
                                x: src.clientX - rect.left,
                                y: src.clientY - rect.top,
                            };
                        },
                        start(e) {
                            e.preventDefault();
                            this.drawing = true;
                            const p = this.getPos(e);
                            this.ctx.beginPath();
                            this.ctx.moveTo(p.x, p.y);
                        },
                        draw(e) {
                            if (!this.drawing) {
                                return;
                            }

                            e.preventDefault();
                            const p = this.getPos(e);
                            this.ctx.lineTo(p.x, p.y);
                            this.ctx.stroke();
                            this.hasInk = true;
                        },
                        end() {
                            if (!this.drawing) {
                                return;
                            }

                            this.drawing = false;

                            if (this.hasInk) {
                                this.save();
                            }
                        },
                        save() {
                            @this.set('signature', this.canvas.toDataURL('image/png'));
                        },
                        clear() {
                            const rect = this.canvas.getBoundingClientRect();
                            this.ctx.clearRect(0, 0, rect.width, rect.height);
                            this.hasInk = false;
                            @this.set('signature', '');
                        },
                        bind() {
                            this.unbind();
                            this._handlers = {
                                mousedown: (e) => this.start(e),
                                mousemove: (e) => this.draw(e),
                                mouseup: () => this.end(),
                                mouseleave: () => this.end(),
                                touchstart: (e) => this.start(e),
                                touchmove: (e) => this.draw(e),
                                touchend: () => this.end(),
                                touchcancel: () => this.end(),
                            };

                            for (const [event, handler] of Object.entries(this._handlers)) {
                                this.canvas.addEventListener(event, handler, { passive: false });
                            }

                            this._onOrientationChange = () => setTimeout(() => this.resize(), 150);
                            window.addEventListener('orientationchange', this._onOrientationChange);
                            this._resizeObserver = new ResizeObserver(() => this.resize());
                            this._resizeObserver.observe(this.canvas);
                        },
                        unbind() {
                            if (this._handlers && this.canvas) {
                                for (const [event, handler] of Object.entries(this._handlers)) {
                                    this.canvas.removeEventListener(event, handler);
                                }
                            }

                            this._resizeObserver?.disconnect();
                            if (this._onOrientationChange) {
                                window.removeEventListener('orientationchange', this._onOrientationChange);
                            }
                        },
                    };

                    pad.resize();
                    pad.bind();

                    return pad;
                }

                window.clearSignature = () => {
                    if (pad?.canvas?.isConnected) {
                        pad.clear();
                        return;
                    }

                    createPad()?.clear();
                };

                function boot() {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => createPad());
                    });
                }

                document.addEventListener('livewire:initialized', () => {
                    boot();

                    Livewire.on('clear-signature', () => window.clearSignature());
                    Livewire.on('stop-changed', () => {
                        window.clearSignature();
                        boot();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    });
                });

                document.addEventListener('livewire:navigated', boot);

                if (document.readyState !== 'loading') {
                    boot();
                }
            })();
        </script>
    @endpush
</x-filament-panels::page>
