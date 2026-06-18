@props([
    'label',
    'value',
    'meta' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['koylu-kpi block']) }}
>
    <p class="koylu-kpi-label">{{ $label }}</p>
    <p class="koylu-kpi-value">{{ $value }}</p>
    @if ($meta)
        <p class="koylu-kpi-meta">{{ $meta }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
