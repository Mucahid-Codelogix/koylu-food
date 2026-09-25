@props([
    'orderItemId',
    'orderedQuantity',
    'isCorrected' => false,
])

@php
    $formattedOrdered = rtrim(rtrim(number_format((float) $orderedQuantity, 2, ',', ''), '0'), ',');
@endphp

<div {{ $attributes->merge(['class' => 'mt-2']) }}>
    <div class="flex items-center justify-end gap-2">
        <label for="loaded-qty-{{ $orderItemId }}" class="text-xs text-gray-400 shrink-0">geladen</label>

        <div class="flex items-center rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
            <button
                type="button"
                wire:click="stepLoadedQuantity({{ $orderItemId }}, -1)"
                class="px-3 py-2.5 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100"
                aria-label="Eén minder"
            >
                <x-heroicon-o-minus class="w-4 h-4" />
            </button>

            <input
                id="loaded-qty-{{ $orderItemId }}"
                type="number"
                step="any"
                min="0"
                inputmode="decimal"
                wire:model.blur="loadingData.{{ $orderItemId }}.loaded_quantity"
                wire:change="saveRow({{ $orderItemId }})"
                @class([
                    'w-16 text-center text-base font-semibold border-0 border-x px-1 py-2.5 dark:bg-gray-900 focus:ring-0 focus:outline-none',
                    'border-gray-200 dark:border-gray-600' => ! $isCorrected,
                    'border-amber-400 bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300' => $isCorrected,
                ])
            />

            <button
                type="button"
                wire:click="stepLoadedQuantity({{ $orderItemId }}, 1)"
                class="px-3 py-2.5 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800 active:bg-gray-100"
                aria-label="Eén meer"
            >
                <x-heroicon-o-plus class="w-4 h-4" />
            </button>
        </div>
    </div>

    @if ($isCorrected)
        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400 text-right">
            Correctie: origineel {{ $formattedOrdered }}
        </p>
    @endif
</div>
