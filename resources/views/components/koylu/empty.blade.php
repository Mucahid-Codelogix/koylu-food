@props([
    'title',
    'text' => null,
])

<div {{ $attributes->class(['koylu-empty']) }}>
    @if (isset($icon))
        <div class="koylu-empty-icon">{{ $icon }}</div>
    @endif
    <p class="koylu-empty-title">{{ $title }}</p>
    @if ($text)
        <p class="koylu-empty-text">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
