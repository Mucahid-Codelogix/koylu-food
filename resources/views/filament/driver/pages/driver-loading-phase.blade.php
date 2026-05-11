<x-filament-panels::page>
    @php
        $product = $this->getCurrentProduct();
        $total   = $this->getTotalProducts();
        $current = $this->currentProductIndex + 1;
        $isLast  = $current === $total;
        $isFirst = $this->currentProductIndex === 0;
    @endphp

    {{-- Progress header --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-4 space-y-2">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-cube class="w-4 h-4 text-orange-500" />
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">
                    Product {{ $current }} van {{ $total }}
                </p>
            </div>
            <span class="text-xs font-semibold text-orange-600 bg-orange-50 px-2.5 py-1 rounded-full">
                Laadproces
            </span>
        </div>
        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
            <div
                class="bg-orange-500 h-2 rounded-full transition-all duration-300"
                style="width: {{ ($current / $total) * 100 }}%"
            ></div>
        </div>
    </div>

    @if ($product)
        {{-- Product kaart --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">

            {{-- Product header --}}
            <div class="bg-orange-500 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2">
                        <x-heroicon-o-cube class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <p class="text-orange-100 text-xs font-medium">Huidig product</p>
                        <p class="text-white text-lg font-bold">{{ $product['name'] }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-orange-100 text-xs">Totaal te laden</p>
                    <p class="text-white text-2xl font-bold">
                        {{ $product['total'] }}
                        <span class="text-sm font-normal">{{ $product['unit'] }}</span>
                    </p>
                </div>
            </div>

            {{-- Klanten tabel --}}
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-8">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Klant</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Stad</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Aantal</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($product['customers'] as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="px-4 py-3">
                                <span class="w-6 h-6 rounded-full bg-orange-100 text-orange-700 text-xs font-bold flex items-center justify-center">
                                    {{ $row['stop_order'] }}
                                </span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800 dark:text-white">{{ $row['customer_name'] }}</p>
                            <p class="text-xs text-gray-400 sm:hidden">{{ $row['city'] }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ $row['city'] }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800 dark:text-white">
                            {{ $row['quantity'] }}
                            <span class="text-xs font-normal text-gray-400">{{ $product['unit'] }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-xs font-semibold text-gray-500 text-right uppercase tracking-wide">Totaal</td>
                    <td class="px-4 py-3 text-right font-bold text-orange-600">
                        {{ $product['total'] }}
                        <span class="text-xs font-normal">{{ $product['unit'] }}</span>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>

        {{-- Navigatie knoppen --}}
        <div class="grid grid-cols-2 gap-3">
            <button
                wire:click="previousProduct"
                @disabled($isFirst)
                class="flex items-center justify-center gap-2 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm disabled:opacity-40 hover:bg-gray-50 transition"
            >
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Vorige
            </button>

            @if ($isLast)
                <button
                    wire:click="finishLoading"
                    class="flex items-center justify-center gap-2 py-3.5 rounded-xl bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold text-sm transition"
                >
                    <x-heroicon-o-check-circle class="w-4 h-4" />
                    Klaar met laden
                </button>
            @else
                <button
                    wire:click="nextProduct"
                    class="flex items-center justify-center gap-2 py-3.5 rounded-xl bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold text-sm transition"
                >
                    Voltooid
                    <x-heroicon-o-arrow-right class="w-4 h-4" />
                </button>
            @endif
        </div>

        {{-- Productenoverzicht inklapbaar --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
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

            <div class="hidden divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($products as $index => $p)
                    <button
                        wire:click="$set('currentProductIndex', {{ $index }})"
                        @class([
                            'w-full flex items-center justify-between px-5 py-3 text-sm transition',
                            'bg-orange-50 dark:bg-orange-900/20' => $index === $currentProductIndex,
                            'hover:bg-gray-50 dark:hover:bg-gray-800' => $index !== $currentProductIndex,
                        ])
                    >
                        <div class="flex items-center gap-3">
                            <span @class([
                                'w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                'bg-orange-500 text-white' => $index === $currentProductIndex,
                                'bg-gray-100 text-gray-500' => $index !== $currentProductIndex,
                            ])>
                                {{ $index + 1 }}
                            </span>
                            <span @class([
                                'font-medium',
                                'text-orange-700 dark:text-orange-400' => $index === $currentProductIndex,
                                'text-gray-700 dark:text-gray-300' => $index !== $currentProductIndex,
                            ])>
                                {{ $p['name'] }}
                            </span>
                        </div>
                        <span class="text-gray-400 text-xs shrink-0">{{ $p['total'] }} {{ $p['unit'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
