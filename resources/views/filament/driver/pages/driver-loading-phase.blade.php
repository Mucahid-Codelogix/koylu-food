<x-filament-panels::page>
    @php
        $product = $this->getCurrentProduct();
        $total   = $this->getTotalProducts();
        $current = $this->currentProductIndex + 1;
        $isLast  = $current === $total;
        $isFirst = $this->currentProductIndex === 0;
        $isWholeChicken = $product['is_whole_chicken'] ?? false;
        $customerCount = count($product['customers'] ?? []);
    @endphp

    {{-- Onderin zit een vaste balk met de hoofdknop; de padding houdt de inhoud vrij. --}}
    <div class="max-w-[1400px] space-y-3 pb-28 sm:pb-24">
        @if ($product)
            {{-- Smalle kopstrook: wat en hoeveel, met de productenlijst die eronder uitschuift --}}
            <div x-data="{ menu: false }">
                <div
                    class="sticky top-0 z-20 flex items-center justify-between gap-2 bg-primary-600 px-3 py-2 text-white shadow-sm"
                    :class="menu ? 'rounded-t-xl' : 'rounded-xl'"
                >
                    <div class="min-w-0">
                        <p class="text-[11px] leading-none text-primary-100 truncate">
                            Product {{ $current }}/{{ $total }}
                            @if (! empty($product['single_supplier']))
                                · {{ $product['single_supplier'] }}
                            @endif
                        </p>
                        <p class="mt-0.5 truncate text-base font-bold leading-tight">{{ $product['name'] }}</p>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <div class="text-right leading-tight">
                            <p class="text-2xl font-extrabold tabular-nums leading-none">
                                {{ $this->formatBoxes($product['total']) }}
                                <span class="text-[11px] font-medium">{{ $this->totalUnitLabel($product) }}</span>
                            </p>
                            @if ($isWholeChicken)
                                <p class="text-[11px] text-primary-100 tabular-nums">
                                    {{ number_format($product['total_pieces'] ?? 0, 0, ',', '.') }} st
                                    · {{ number_format($product['total_kg'] ?? 0, 2, ',', '.') }} kg
                                </p>
                            @endif
                        </div>

                        <a
                            href="{{ route('leverbon.route', $this->route) }}"
                            target="_blank"
                            class="ml-1 inline-flex items-center justify-center rounded-lg bg-white/15 p-2.5 text-white transition hover:bg-white/25"
                            title="Leverbonnen downloaden"
                            aria-label="Leverbonnen downloaden"
                        >
                            <x-heroicon-o-printer class="w-5 h-5" />
                        </a>

                        <button
                            type="button"
                            @click="menu = ! menu"
                            class="inline-flex items-center justify-center rounded-lg bg-white/15 p-2.5 text-white transition hover:bg-white/25"
                            :aria-expanded="menu"
                            aria-label="Alle producten"
                            title="Alle producten"
                        >
                            <x-heroicon-o-list-bullet class="w-5 h-5 transition" ::class="menu && 'rotate-0 opacity-90'" />
                        </button>
                    </div>
                </div>

                {{-- Schuift de inhoud naar beneden in plaats van eroverheen te liggen --}}
                <div
                    x-show="menu"
                    x-cloak
                    class="overflow-hidden rounded-b-xl border border-t-0 border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <p class="px-4 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                        Alle producten ({{ $total }})
                    </p>

                    <div class="divide-y divide-gray-50 dark:divide-gray-800/80">
                        @foreach ($products as $index => $p)
                            <button
                                wire:click="$set('currentProductIndex', {{ $index }})"
                                @click="menu = false"
                                @class([
                                    'w-full flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition',
                                    'bg-primary-50 dark:bg-primary-900/20' => $index === $this->currentProductIndex,
                                    'hover:bg-gray-50 dark:hover:bg-gray-800' => $index !== $this->currentProductIndex,
                                ])
                            >
                                <span class="flex items-center gap-3 min-w-0">
                                    <span @class([
                                        'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                        'bg-primary-500 text-white' => $index === $this->currentProductIndex,
                                        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $index !== $this->currentProductIndex,
                                    ])>
                                        {{ $index + 1 }}
                                    </span>
                                    <span @class([
                                        'font-medium truncate',
                                        'text-primary-700 dark:text-primary-400' => $index === $this->currentProductIndex,
                                        'text-gray-700 dark:text-gray-300' => $index !== $this->currentProductIndex,
                                    ])>
                                        {{ $p['name'] }}
                                    </span>
                                </span>
                                <span class="text-gray-700 dark:text-gray-200 text-sm font-bold shrink-0 tabular-nums">
                                    {{ $this->formatBoxes($p['total']) }} {{ $this->totalUnitLabel($p) }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-start">

                {{-- Wat er in de auto moet --}}
                @php
                    $loadRows = $isWholeChicken
                        ? ($product['variant_breakdown'] ?? [])
                        : ($product['packaging_breakdown'] ?? []);
                @endphp

                @if (count($loadRows) > ($isWholeChicken ? 0 : 1))
                    <x-koylu.card class="lg:col-span-2 lg:sticky lg:top-16">
                        <p class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 bg-gray-50 dark:bg-gray-800/60 border-b border-gray-100 dark:border-gray-800">
                            {{ $isWholeChicken ? 'Per gramvariant laden' : 'Per verpakking laden' }}
                        </p>

                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($loadRows as $loadRow)
                                {{-- Mobiel en in de smalle tabletkolom: variant en aantal boven, details eronder.
                                     Op tablet in één kolom past alles op één regel. --}}
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-3 py-2">
                                    <span class="order-1 shrink-0 whitespace-nowrap rounded-md bg-gray-100 px-2 py-0.5 text-sm font-bold text-gray-900 dark:bg-gray-800 dark:text-white">
                                        {{ $loadRow['label'] }}
                                    </span>

                                    <span class="order-2 ml-auto shrink-0 whitespace-nowrap tabular-nums sm:order-3 lg:order-2">
                                        <span class="text-xl font-extrabold text-gray-900 dark:text-white">
                                            {{ $isWholeChicken ? $this->formatBoxes($loadRow['boxes']) : $this->formatBoxes($loadRow['quantity']).'x' }}
                                        </span>
                                        @if ($isWholeChicken)
                                            <span class="text-[11px] font-semibold text-gray-500">doos</span>
                                        @endif
                                    </span>

                                    <p class="order-3 w-full text-[11px] leading-snug text-gray-500 dark:text-gray-400 sm:order-2 sm:w-auto sm:flex-1 sm:pl-0 lg:order-3 lg:w-full ">
                                        @if ($isWholeChicken)
                                            {{ number_format($loadRow['pieces'], 0, ',', '.') }} st
                                            · {{ number_format($loadRow['kg'], 2, ',', '.') }} kg
                                        @else
                                            à {{ $this->formatBoxes($loadRow['box_weight_kg']) }} kg
                                            · {{ number_format($loadRow['kg'], 2, ',', '.') }} kg totaal
                                        @endif

                                        @if (($product['has_mixed_suppliers'] ?? false) && ! empty($loadRow['supplier_breakdown']))
                                            <span class="block">
                                                @foreach ($loadRow['supplier_breakdown'] as $supplierName => $supplierBoxes)
                                                    {{ $supplierName }} {{ $this->formatBoxes($supplierBoxes) }}x{{ $loop->last ? '' : ' · ' }}
                                                @endforeach
                                            </span>
                                        @endif

                                        @if (! empty($loadRow['substituted_from']))
                                            <span class="block font-medium text-amber-700 dark:text-amber-500">
                                                @foreach ($loadRow['substituted_from'] as $orderedLabel => $orderedBoxes)
                                                    {{ $this->formatBoxes($orderedBoxes) }}x i.p.v. {{ $orderedLabel }}{{ $loop->last ? '' : ' · ' }}
                                                @endforeach
                                            </span>
                                        @endif
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </x-koylu.card>
                @endif

                {{-- Per klant een eigen kaart, zodat elke klant een eigen eilandje is --}}
                <div @class([
                    'space-y-3',
                    'lg:col-span-3' => count($loadRows) > ($isWholeChicken ? 0 : 1),
                    'lg:col-span-5' => count($loadRows) <= ($isWholeChicken ? 0 : 1),
                ])>
                    <div class="flex items-center justify-between gap-3 px-1">
                        <span class="flex items-center gap-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                            <x-heroicon-o-users class="w-3.5 h-3.5 text-gray-400" />
                            Per klant
                        </span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums',
                            'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => ($product['checked_count'] ?? 0) === $customerCount && $customerCount > 0,
                            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' => ($product['checked_count'] ?? 0) !== $customerCount,
                        ])>
                            {{ $product['checked_count'] ?? 0 }}/{{ $customerCount }} geladen
                        </span>
                    </div>

                    <div class="space-y-3">
                        @if ($isWholeChicken)
                            <div class="space-y-3">
                                @foreach ($this->customerGroups($product) as $group)
                                    <x-koylu.card wire:key="group-{{ $product['id'] }}-{{ $group['stop_order'] }}">
                                        <div class="flex items-center gap-2 px-3 py-2.5 sm:px-4 border-b border-gray-100 dark:border-gray-800">
                                            <span class="koylu-stop-dot-pending w-6 h-6 text-[10px] shrink-0">{{ $group['stop_order'] }}</span>
                                            <p class="font-semibold text-gray-800 dark:text-white truncate">{{ $group['customer_name'] }}</p>
                                            @if (filled($group['city']))
                                                <span class="text-xs text-gray-400 truncate">{{ $group['city'] }}</span>
                                            @endif
                                        </div>

                                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($group['rows'] as $row)
                                                @php
                                                    $itemId = $row['order_item_id'];
                                                    $ordered = (float) $row['quantity'];
                                                    $canSubstitute = ($row['allows_substitute'] ?? false) || ($product['allows_substitute'] ?? false);
                                                    $loadedRows = $row['loaded_rows'] ?? [];
                                                    $isChanged = collect($loadedRows)->contains(
                                                        fn (array $loaded): bool => $loaded['is_substituted'] || ! is_null($loaded['weight_kg'])
                                                    ) || count($loadedRows) > 1;
                                                    $breakdownRows = $this->boxBreakdowns[$itemId] ?? [];
                                                    $shown = collect($breakdownRows)
                                                        ->map(fn (array $boxRow): bool => (float) str_replace(',', '.', (string) $boxRow['quantity']) > 0)
                                                        ->values()
                                                        ->all();
                                                    $assignedOnServer = $this->assignedBoxes($itemId);
                                                    $rowSignature = md5(json_encode($breakdownRows).$row['loaded_quantity'].count($loadedRows));
                                                @endphp

                                                <div
                                                    wire:key="row-{{ $itemId }}-{{ $rowSignature }}"
                                                    x-data="{
                                                        open: false,
                                                        ordered: @js($ordered),
                                                        assigned: @js($assignedOnServer),
                                                        shown: @js($shown),
                                                        qtyInputs() {
                                                            return Array.from($el.querySelectorAll('[data-box-qty]'));
                                                        },
                                                        number(input) {
                                                            return parseFloat(String(input.value).replace(',', '.')) || 0;
                                                        },
                                                        recount() {
                                                            this.assigned = this.qtyInputs()
                                                                .filter((input) => this.shown[Number(input.dataset.index)])
                                                                .reduce((sum, input) => sum + this.number(input), 0);
                                                        },
                                                        touch() {
                                                            $wire.set('breakdownTouched.{{ $itemId }}', true, false);
                                                        },
                                                        setValue(index, value) {
                                                            const input = this.qtyInputs().find((i) => Number(i.dataset.index) === index);

                                                            if (! input) {
                                                                return;
                                                            }

                                                            input.value = Math.max(0, value);
                                                            input.dispatchEvent(new Event('input'));
                                                        },
                                                        step(index, delta) {
                                                            const input = this.qtyInputs().find((i) => Number(i.dataset.index) === index);
                                                            this.setValue(index, this.number(input) + delta);
                                                            this.recount();
                                                            this.touch();
                                                        },
                                                        addRow() {
                                                            const index = this.shown.findIndex((visible) => ! visible);

                                                            if (index === -1) {
                                                                return;
                                                            }

                                                            this.shown[index] = true;
                                                            this.setValue(index, 1);
                                                            this.recount();
                                                            this.touch();
                                                        },
                                                        removeRow(index) {
                                                            this.shown[index] = false;
                                                            this.setValue(index, 0);
                                                            this.recount();
                                                            this.touch();
                                                        },
                                                    }"
                                                    x-init="recount()"
                                                    class="p-2.5"
                                                >
                                                    <div class="flex items-center justify-between gap-3">
                                                        <button
                                                            type="button"
                                                            wire:click="toggleRowChecked({{ $itemId }})"
                                                            wire:loading.class="opacity-50"
                                                            wire:target="toggleRowChecked({{ $itemId }})"
                                                            @class([
                                                                'shrink-0 w-11 h-11 rounded-xl border flex items-center justify-center transition',
                                                                'border-success-500 bg-success-500 text-white' => $row['is_checked'],
                                                                'border-gray-300 dark:border-gray-600 text-gray-300 dark:text-gray-600' => ! $row['is_checked'],
                                                            ])
                                                            aria-label="{{ $row['is_checked'] ? 'Markeer als niet geladen' : 'Markeer als geladen' }}"
                                                        >
                                                            <x-heroicon-o-check class="w-6 h-6" />
                                                        </button>

                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-base font-bold tabular-nums text-gray-900 dark:text-white">
                                                                {{ $this->formatBoxes($ordered) }} {{ $ordered === 1.0 ? 'doos' : 'dozen' }}
                                                                · {{ $row['ordered_variant_label'] }}
                                                            </p>
                                                            <p class="text-xs text-gray-400">
                                                                {{ number_format($row['ordered_total_weight_kg'] ?? 0, 2, ',', '.') }} kg volgens variant
                                                                @if (($product['has_mixed_suppliers'] ?? false) && filled($row['supplier_name'] ?? null))
                                                                    · {{ $row['supplier_name'] }}
                                                                @endif
                                                            </p>

                                                            @if ($isChanged)
                                                                <div class="mt-1 space-y-0.5">
                                                                    @foreach ($loadedRows as $loaded)
                                                                        <p class="text-xs font-medium text-amber-700 dark:text-amber-500 tabular-nums">
                                                                            Geladen: {{ $this->formatBoxes($loaded['boxes']) }}x {{ $loaded['label'] }}
                                                                            @if (! is_null($loaded['weight_kg']))
                                                                                · gewogen {{ number_format($loaded['weight_kg'], 2, ',', '.') }} kg
                                                                            @endif
                                                                        </p>
                                                                    @endforeach
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <button
                                                            type="button"
                                                            @click="open = ! open; $nextTick(() => recount())"
                                                            class="koylu-btn-secondary !w-auto shrink-0 !px-4 !py-2.5 text-xs"
                                                            x-text="open ? 'Sluiten' : 'Wijzigen'"
                                                        >Wijzigen</button>
                                                    </div>

                                                    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="mt-3 space-y-2">
                                                        @foreach ($breakdownRows as $index => $boxRow)
                                                            <div wire:key="box-{{ $itemId }}-{{ $index }}" x-show="shown[{{ $index }}]" x-cloak class="flex items-center gap-2">
                                                                <div class="flex items-center rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden shrink-0">
                                                                    <button
                                                                        type="button"
                                                                        @click="step({{ $index }}, -1)"
                                                                        class="px-2.5 py-2.5 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                                        aria-label="Eén doos minder"
                                                                    >
                                                                        <x-heroicon-o-minus class="w-4 h-4" />
                                                                    </button>
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        step="1"
                                                                        inputmode="numeric"
                                                                        data-box-qty
                                                                        data-index="{{ $index }}"
                                                                        wire:model="boxBreakdowns.{{ $itemId }}.{{ $index }}.quantity"
                                                                        @input="recount(); touch()"
                                                                        aria-label="Aantal dozen"
                                                                        class="w-12 text-center text-base font-semibold border-0 border-x border-gray-200 dark:border-gray-600 px-1 py-2.5 dark:bg-gray-900 focus:ring-0 focus:outline-none"
                                                                    />
                                                                    <button
                                                                        type="button"
                                                                        @click="step({{ $index }}, 1)"
                                                                        class="px-2.5 py-2.5 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                                        aria-label="Eén doos meer"
                                                                    >
                                                                        <x-heroicon-o-plus class="w-4 h-4" />
                                                                    </button>
                                                                </div>

                                                                <select
                                                                    wire:model="boxBreakdowns.{{ $itemId }}.{{ $index }}.variant_id"
                                                                    @change="touch()"
                                                                    @disabled(! $canSubstitute)
                                                                    aria-label="Gramvariant"
                                                                    class="flex-1 min-w-0 text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-2.5 dark:bg-gray-900 disabled:opacity-60"
                                                                >
                                                                    @foreach ($row['gram_variants'] as $variant)
                                                                        <option value="{{ $variant->id }}">{{ $variant->displayLabel() }}</option>
                                                                    @endforeach
                                                                </select>

                                                                <input
                                                                    type="number"
                                                                    step="any"
                                                                    min="0"
                                                                    inputmode="decimal"
                                                                    wire:model="boxBreakdowns.{{ $itemId }}.{{ $index }}.weight_kg"
                                                                    @input="touch()"
                                                                    placeholder="kg"
                                                                    aria-label="Gewogen kilo"
                                                                    class="w-20 shrink-0 text-right text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-2.5 dark:bg-gray-900"
                                                                />

                                                                <button
                                                                    type="button"
                                                                    @click="removeRow({{ $index }})"
                                                                    class="shrink-0 p-2.5 text-gray-400 hover:text-danger-600"
                                                                    aria-label="Regel verwijderen"
                                                                >
                                                                    <x-heroicon-o-trash class="w-5 h-5" />
                                                                </button>
                                                            </div>
                                                        @endforeach

                                                        <div
                                                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium"
                                                            :class="assigned === ordered
                                                                ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400'
                                                                : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400'"
                                                        >
                                                            <x-heroicon-o-check class="w-4 h-4 shrink-0" x-show="assigned === ordered" />
                                                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 shrink-0" x-show="assigned !== ordered" x-cloak />
                                                            <span>
                                                                <span x-text="Number(assigned.toFixed(2))">{{ $this->formatBoxes($assignedOnServer) }}</span>
                                                                van {{ $this->formatBoxes($ordered) }} dozen toegewezen
                                                            </span>
                                                        </div>

                                                        @if ($canSubstitute)
                                                            <input
                                                                type="text"
                                                                wire:model="loadingData.{{ $itemId }}.substitution_reason"
                                                                @input="touch()"
                                                                placeholder="Reden bij een andere variant, bv. niet op voorraad"
                                                                class="w-full text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-2.5 dark:bg-gray-900"
                                                            />
                                                        @endif

                                                        <div class="flex items-center gap-2">
                                                            <button type="button" @click="addRow()" class="koylu-btn-secondary !w-auto !px-4 !py-2.5 text-xs">
                                                                <x-heroicon-o-plus class="w-4 h-4" />
                                                                Regel
                                                            </button>
                                                            <button
                                                                type="button"
                                                                wire:click="saveBoxBreakdown({{ $itemId }})"
                                                                @click="open = false"
                                                                class="koylu-btn-primary !w-auto !px-4 !py-2.5 text-xs"
                                                            >
                                                                <span wire:loading.remove wire:target="saveBoxBreakdown({{ $itemId }})">Opslaan</span>
                                                                <span wire:loading wire:target="saveBoxBreakdown({{ $itemId }})">Bezig...</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </x-koylu.card>
                                @endforeach
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($product['customers'] as $row)
                                    <x-koylu.card wire:key="std-{{ $row['order_item_id'] }}" class="px-3 py-3 sm:px-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <button
                                                type="button"
                                                wire:click="toggleRowChecked({{ $row['order_item_id'] }})"
                                                @class([
                                                    'shrink-0 w-11 h-11 rounded-xl border flex items-center justify-center transition',
                                                    'border-success-500 bg-success-500 text-white' => $row['is_checked'],
                                                    'border-gray-300 dark:border-gray-600 text-gray-300 dark:text-gray-600' => ! $row['is_checked'],
                                                ])
                                                aria-label="{{ $row['is_checked'] ? 'Markeer als niet geladen' : 'Markeer als geladen' }}"
                                            >
                                                <x-heroicon-o-check class="w-6 h-6" />
                                            </button>

                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="koylu-stop-dot-pending w-6 h-6 text-[10px] shrink-0">{{ $row['stop_order'] }}</span>
                                                    <p class="font-semibold truncate text-gray-800 dark:text-white">{{ $row['customer_name'] }}</p>
                                                </div>
                                                <p class="mt-1 text-xs text-gray-400">
                                                    {{ $row['city'] }}
                                                    @if (($product['has_mixed_suppliers'] ?? false) && filled($row['supplier_name'] ?? null))
                                                        · {{ $row['supplier_name'] }}
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="text-right shrink-0 tabular-nums">
                                                <span class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $this->formatBoxes($row['quantity']) }}</span>
                                                <span class="text-sm font-bold text-gray-600 dark:text-gray-300 ml-0.5">{{ $row['unit'] }}</span>
                                            </div>
                                        </div>

                                        <div class="mt-2 flex flex-wrap items-end justify-end gap-3">
                                            <x-koylu.loaded-quantity-input
                                                :order-item-id="$row['order_item_id']"
                                                :ordered-quantity="$row['quantity']"
                                                :is-corrected="$this->isQuantityCorrected($row['order_item_id'], $row['quantity'])"
                                            />

                                            @if (! empty($this->savedRows[$row['order_item_id']]))
                                                <span
                                                    wire:key="saved-{{ $row['order_item_id'] }}-{{ $this->savedRows[$row['order_item_id']] }}"
                                                    x-data="{ show: true }"
                                                    x-init="setTimeout(() => show = false, 2500)"
                                                    x-show="show"
                                                    x-transition.opacity
                                                    class="inline-flex items-center gap-1 text-xs font-medium text-success-600 dark:text-success-400"
                                                >
                                                    <x-heroicon-o-check-circle class="w-4 h-4" />
                                                    Opgeslagen
                                                </span>
                                            @endif

                                            <div class="flex items-center gap-1">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    inputmode="decimal"
                                                    wire:model.blur="loadingData.{{ $row['order_item_id'] }}.actual_weight_kg"
                                                    wire:change="saveRow({{ $row['order_item_id'] }})"
                                                    placeholder="0.000"
                                                    aria-label="Werkelijk gewicht"
                                                    class="w-24 text-right text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-2.5 dark:bg-gray-900 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                                />
                                                <span class="text-xs text-gray-400 shrink-0">kg</span>
                                            </div>
                                        </div>
                                    </x-koylu.card>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Vaste balk: voortgang links, hoofdknop rechts onder de duim --}}
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur pb-[env(safe-area-inset-bottom)]">
                <div class="mx-auto max-w-7xl flex items-center gap-2 px-3 py-2">
                    <button
                        wire:click="previousProduct"
                        @disabled($isFirst)
                        class="koylu-btn-secondary !w-auto shrink-0 !px-3.5 !py-2.5 disabled:opacity-40"
                        aria-label="Vorige product"
                    >
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </button>

                    <div class="flex-1 min-w-0">
                        <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $total > 0 ? round($current / $total * 100) : 0 }}%"></div>
                        </div>
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">{{ $current }} / {{ $total }}</p>
                    </div>

                    @if ($isLast)
                        <button wire:click="finishLoading" class="koylu-btn-success !w-auto shrink-0 !px-4 !py-2.5">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            Klaar
                        </button>
                    @else
                        <button wire:click="nextProduct" class="koylu-btn-primary !w-auto shrink-0 !px-4 !py-2.5">
                            Voltooid
                            <x-heroicon-o-arrow-right class="w-5 h-5" />
                        </button>
                    @endif
                </div>
            </div>
        @else
            <x-koylu.empty title="Niets te laden" text="Op deze route staan geen producten. Neem contact op met de planner.">
                <x-slot:icon>
                    <x-heroicon-o-cube class="w-full h-full" />
                </x-slot:icon>
                <a href="{{ \App\Filament\Driver\Pages\DriverDashboard::getUrl() }}" class="koylu-link mt-3">← Terug naar Mijn Route</a>
            </x-koylu.empty>
        @endif
    </div>
</x-filament-panels::page>
