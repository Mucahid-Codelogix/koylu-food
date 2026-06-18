@props([
    'variant' => 'neutral',
])

@php
    $class = match ($variant) {
        'success' => 'koylu-badge-success',
        'warning' => 'koylu-badge-warning',
        'danger' => 'koylu-badge-danger',
        'primary' => 'koylu-badge-primary',
        default => 'koylu-badge-neutral',
    };
@endphp

<span {{ $attributes->class([$class]) }}>
    {{ $slot }}
</span>
