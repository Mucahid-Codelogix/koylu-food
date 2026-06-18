@props([
    'variant' => 'danger',
])

@php
    $class = $variant === 'warning' ? 'koylu-alert-warning' : 'koylu-alert-danger';
@endphp

<div {{ $attributes->class([$class]) }}>
    {{ $slot }}
</div>
