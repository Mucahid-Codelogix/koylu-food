<div {{ $attributes->class(['koylu-card']) }}>
    @isset($header)
        <div class="koylu-card-header">
            {{ $header }}
        </div>
    @endisset

    {{ $slot }}

    @isset($footer)
        <div class="koylu-card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
