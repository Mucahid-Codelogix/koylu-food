<x-filament-panels::page>
    @php
        $product = $this->getCurrentProduct();
        $total   = $this->getTotalProducts();
        $current = $this->currentProductIndex + 1;
        $isLast  = $current === $total;
        $isFirst = $this->currentProductIndex === 0;
    @endphp

    <div class="koylu-page">
        <x-koylu.phase-progress
            :current="$current"
            :total="$total"
            :label="'Product ' . $current . ' van ' . $total"
            phase="Laadproces"
        />

        @if ($product)
            <x-koylu.card>
                <div class="koylu-card-hero">
                    <div class="flex items-center gap-3">
                        <div class="koylu-card-hero-icon">
                            <x-heroicon-o-cube class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <p class="koylu-card-hero-label">Huidig product</p>
                            <p class="text-white text-lg font-bold">{{ $product['name'] }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="koylu-card-hero-label">Totaal te laden</p>
                        <p class="text-white text-2xl font-bold tabular-nums">
                            {{ $product['total'] }}
                            <span class="text-sm font-normal">doos</span>
                        </p>
                        @if ($product['is_whole_chicken'] ?? false)
                            <p class="koylu-card-hero-label mt-0.5">
                                {{ number_format($product['total_pieces'] ?? 0, 0, ',', '.') }} st
                                · {{ number_format($product['total_kg'] ?? 0, 2, ',', '.') }} kg
                            </p>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="koylu-table">
                        <thead>
                            <tr>
                                <th class="w-8">#</th>
                                <th>Klant</th>
                                <th class="hidden sm:table-cell">Stad</th>
                                <th class="text-right">Aantal / Gewicht</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($product['customers'] as $row)
                                <tr class="align-top">
                                    <td>
                                        <span class="koylu-stop-dot-pending w-6 h-6 text-[10px]">
                                            {{ $row['stop_order'] }}
                                        </span>
                                    </td>
                                    <td colspan="{{ ($product['is_whole_chicken'] ?? false) ? 2 : 1 }}">
                                        <p class="font-medium text-gray-800 dark:text-white">{{ $row['customer_name'] }}</p>
                                        <p class="text-xs text-gray-400">{{ $row['city'] }}</p>

                                        @if ($row['is_whole_chicken'] ?? false)
                                            <div class="mt-3 space-y-2 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/60">
                                                <p class="text-xs text-gray-500">
                                                    Besteld: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $row['ordered_variant_label'] }}</span>
                                                    · {{ $row['quantity'] }} doos
                                                    · {{ number_format($row['ordered_pieces'] ?? 0, 0, ',', '.') }} st
                                                    · {{ number_format($row['ordered_total_weight_kg'] ?? 0, 2, ',', '.') }} kg
                                                </p>

                                                <div class="mt-2">
                                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Werkelijk totaalgewicht</label>
                                            <div class="mt-1 flex items-center gap-1">
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0"
                                                    wire:model="loadingData.{{ $row['order_item_id'] }}.actual_weight_kg"
                                                    placeholder="0.000"
                                                    class="w-28 text-right text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1.5 dark:bg-gray-900 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                                />
                                                <span class="text-xs text-gray-400">kg</span>
                                            </div>
                                        </div>

                                        @if (($row['allows_substitute'] ?? false) || ($product['allows_substitute'] ?? false))
                                                    <div>
                                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Geladen variant</label>
                                                        <select
                                                            wire:model="loadingData.{{ $row['order_item_id'] }}.loaded_gram_variant_id"
                                                            class="mt-1 w-full text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1.5 dark:bg-gray-900"
                                                        >
                                                            @foreach ($row['gram_variants'] as $variant)
                                                                <option value="{{ $variant->id }}">{{ $variant->boxDescription() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div>
                                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Reden bij alternatief (indien van toepassing)</label>
                                                        <input
                                                            type="text"
                                                            wire:model="loadingData.{{ $row['order_item_id'] }}.substitution_reason"
                                                            placeholder="Bv. niet op voorraad"
                                                            class="mt-1 w-full text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1.5 dark:bg-gray-900"
                                                        />
                                                    </div>

                                                    <button
                                                        type="button"
                                                        wire:click="saveLoadedVariant({{ $row['order_item_id'] }})"
                                                        class="koylu-link"
                                                    >
                                                        Variant opslaan
                                                    </button>
                                                @else
                                                    <p class="text-xs text-gray-600 dark:text-gray-300">
                                                        Geladen zoals besteld: <span class="font-semibold">{{ $row['ordered_variant_label'] }}</span>
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    @if (! ($product['is_whole_chicken'] ?? false))
                                        <td class="text-gray-500 hidden sm:table-cell">{{ $row['city'] }}</td>
                                        <td class="text-right tabular-nums">
                                            <span class="text-xl font-extrabold text-gray-900 dark:text-white">{{ $row['quantity'] }}</span>
                                            <span class="text-sm font-bold text-gray-600 dark:text-gray-300 ml-0.5">{{ $product['unit'] }}</span>
                                            <div class="mt-2 flex items-center justify-end gap-1">
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0"
                                                    wire:model="loadingData.{{ $row['order_item_id'] }}.actual_weight_kg"
                                                    placeholder="0.000"
                                                    class="w-24 text-right text-sm border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 dark:bg-gray-900 focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                                                />
                                                <span class="text-xs text-gray-400 shrink-0">kg</span>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <td colspan="3" class="text-xs font-semibold text-gray-500 text-right uppercase tracking-wide">Totaal</td>
                                <td class="text-right tabular-nums">
                                    <span class="text-xl font-extrabold text-primary-600">{{ $product['total'] }}</span>
                                    <span class="text-sm font-bold text-primary-500 ml-0.5">{{ $product['unit'] }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-koylu.card>

            <div class="grid grid-cols-2 gap-3">
                <button wire:click="previousProduct" @disabled($isFirst) class="koylu-btn-secondary w-full">
                    <x-heroicon-o-arrow-left class="w-4 h-4" />
                    Vorige
                </button>

                @if ($isLast)
                    <button wire:click="finishLoading" class="koylu-btn-success !w-auto col-span-1">
                        <x-heroicon-o-check-circle class="w-4 h-4" />
                        Klaar met laden
                    </button>
                @else
                    <button wire:click="nextProduct" class="koylu-btn-primary !w-auto">
                        Voltooid
                        <x-heroicon-o-arrow-right class="w-4 h-4" />
                    </button>
                @endif
            </div>

            <x-koylu.card>
                <button
                    onclick="this.nextElementSibling.classList.toggle('hidden')"
                    class="w-full flex items-center justify-between px-5 py-4"
                >
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-list-bullet class="w-4 h-4 text-gray-400" />
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Alle producten</span>
                    </div>
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400" />
                </button>

                <div class="hidden divide-y divide-gray-50 dark:divide-gray-800/80 border-t border-gray-100 dark:border-gray-800">
                    @foreach ($products as $index => $p)
                        <button
                            wire:click="$set('currentProductIndex', {{ $index }})"
                            @class([
                                'w-full flex items-center justify-between px-5 py-3 text-sm transition',
                                'bg-primary-50 dark:bg-primary-900/20' => $index === $this->currentProductIndex,
                                'hover:bg-gray-50 dark:hover:bg-gray-800' => $index !== $this->currentProductIndex,
                            ])
                        >
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                    'bg-primary-500 text-white' => $index === $this->currentProductIndex,
                                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $index !== $this->currentProductIndex,
                                ])>
                                    {{ $index + 1 }}
                                </span>
                                <span @class([
                                    'font-medium',
                                    'text-primary-700 dark:text-primary-400' => $index === $this->currentProductIndex,
                                    'text-gray-700 dark:text-gray-300' => $index !== $this->currentProductIndex,
                                ])>
                                    {{ $p['name'] }}
                                </span>
                            </div>
                            <span class="text-gray-700 dark:text-gray-200 text-base font-bold shrink-0 tabular-nums">{{ $p['total'] }} {{ $p['unit'] }}</span>
                        </button>
                    @endforeach
                </div>
            </x-koylu.card>
        @endif
    </div>
</x-filament-panels::page>
