@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <h2 class="koylu-card-title">{{ $title }}</h2>
    @if ($subtitle)
        <p class="koylu-card-subtitle">{{ $subtitle }}</p>
    @endif
</div>
