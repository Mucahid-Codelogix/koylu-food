@props([
    'current',
    'total',
    'label',
    'phase',
    'icon' => 'heroicon-o-cube',
])

<div {{ $attributes->class(['koylu-phase-card']) }}>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <x-dynamic-component :component="$icon" class="w-4 h-4 text-primary-500" />
            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $label }}</p>
        </div>
        <x-koylu.badge variant="primary">{{ $phase }}</x-koylu.badge>
    </div>
    <div class="koylu-progress">
        <div class="koylu-progress-bar" style="width: {{ $total > 0 ? ($current / $total) * 100 : 0 }}%"></div>
    </div>
</div>
