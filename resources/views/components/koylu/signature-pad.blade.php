@props([
    'padId',
    'property',
    'label',
    'hasSignature' => false,
])

<div>
    <label class="text-xs font-medium text-gray-500 block mb-2">{{ $label }}</label>

    <div wire:ignore>
        <canvas
            id="{{ $padId }}"
            data-signature-pad
            data-property="{{ $property }}"
            class="block w-full h-40 max-w-full border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 touch-none"
        ></canvas>
    </div>

    <div class="flex items-center justify-between mt-2">
        <button
            type="button"
            onclick="clearSignature('{{ $padId }}')"
            class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-gray-600 transition"
        >
            <x-heroicon-o-arrow-path class="w-3.5 h-3.5" />
            Opnieuw tekenen
        </button>

        @if ($hasSignature)
            <span class="flex items-center gap-1.5 text-xs text-koylu-green font-medium">
                <x-heroicon-s-check-circle class="w-3.5 h-3.5" />
                Handtekening opgeslagen
            </span>
        @endif
    </div>
</div>
