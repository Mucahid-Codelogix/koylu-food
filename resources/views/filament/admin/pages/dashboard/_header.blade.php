@php
    $exactConnected = $exact['is_connected'] ?? false;
@endphp

<header class="koylu-page-header">
    <div>
        <p class="koylu-page-meta">{{ now()->isoFormat('dddd D MMMM YYYY') }}</p>
        <h1 class="koylu-page-title mt-0.5">Welkom, {{ auth()->user()->name }}</h1>
    </div>

    <div @class([
        'koylu-status-strip shrink-0',
        'koylu-status-strip-success' => $exactConnected,
        'koylu-status-strip-warning' => ! $exactConnected,
    ])>
        <span @class([
            'relative flex h-2.5 w-2.5 shrink-0',
        ])>
            @if ($exactConnected)
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-koylu-green opacity-50"></span>
            @endif
            <span @class([
                'relative inline-flex rounded-full h-2.5 w-2.5',
                'bg-koylu-green' => $exactConnected,
                'bg-amber-500' => ! $exactConnected,
            ])></span>
        </span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                Exact {{ $exactConnected ? 'gekoppeld' : 'offline' }}
            </p>
            @if ($exactConnected)
                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                    Admin {{ $exact['division'] ?? '—' }}
                    @if ($exact['expires_at'])
                        · token t/m {{ $exact['expires_at'] }}
                    @endif
                </p>
            @else
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Boeken niet beschikbaar</p>
            @endif
        </div>
        <a href="{{ $this->exactConnectionUrl() }}" class="koylu-link whitespace-nowrap ml-2">Beheren →</a>
    </div>
</header>
