@props([
    'orderItemId',
    'orderedQuantity',
    'isCorrected' => false,
])

@php
    $formattedOrdered = rtrim(rtrim(number_format((float) $orderedQuantity, 2, ',', ''), '0'), ',');
@endphp

<div {{ $attributes->merge(['class' => 'mt-2']) }}>
    <div class="flex items-center justify-end gap-1">
        <label for="loaded-qty-{{ $orderItemId }}" class="text-xs text-gray-400 shrink-0">geladen</label>
        <input
            id="loaded-qty-{{ $orderItemId }}"
            type="number"
            step="0.01"
            min="0"
            wire:model.blur="loadingData.{{ $orderItemId }}.loaded_quantity"
            @class([
                'w-20 text-right text-sm border rounded-lg px-2 py-1 dark:bg-gray-900 focus:ring-1 focus:ring-primary-500 focus:border-primary-500',
                'border-gray-200 dark:border-gray-600' => ! $isCorrected,
                'border-amber-400 bg-amber-50 dark:bg-amber-900/20 font-semibold text-amber-700 dark:text-amber-300' => $isCorrected,
            ])
        />
    </div>

    @if ($isCorrected)
        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
            Correctie: origineel {{ $formattedOrdered }}
        </p>
    @endif
</div>
