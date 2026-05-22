@props([
    'height' => '2.75rem',
    'alt' => null,
])

<img
    src="{{ asset(config('brand.logo')) }}"
    alt="{{ $alt ?? config('brand.name') }}"
    {{ $attributes->merge(['style' => "height: {$height}; width: auto;"]) }}
/>
