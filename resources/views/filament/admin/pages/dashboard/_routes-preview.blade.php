@php
    use App\Enums\RouteStatus;
@endphp

<x-koylu.card>
    <x-slot:header>
        <x-koylu.section-heading title="Routes vandaag" subtitle="Volgende stop per chauffeur" />
        <a href="{{ $this->routesTodayUrl() }}" class="koylu-link">Overzicht →</a>
    </x-slot:header>

    @if ($driverRoutesPreview->isEmpty())
        <x-koylu.empty title="Geen routes gepland" text="Plan een route voor vandaag">
            <x-slot:icon>
                <x-heroicon-o-truck class="w-full h-full" />
            </x-slot:icon>
            <a href="{{ $this->routesTodayUrl() }}" class="koylu-link mt-3">Route plannen →</a>
        </x-koylu.empty>
    @else
        <ul class="divide-y divide-gray-50 dark:divide-gray-800/80">
            @foreach ($driverRoutesPreview as $driverRoute)
                @php
                    $route = $driverRoute['route'];
                    $status = $route->status;
                    $nextStop = $driverRoute['next_pending_stop'];
                    $badgeVariant = match ($status) {
                        RouteStatus::IN_PROGRESS => 'primary',
                        RouteStatus::COMPLETED => 'success',
                        default => 'neutral',
                    };
                @endphp
                <li class="px-5 py-4">
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $driverRoute['driver_name'] }}
                            </p>
                            @if ($route->vehicle)
                                <p class="text-[11px] text-gray-400">{{ $route->vehicle->license_plate }}</p>
                            @endif
                        </div>
                        <x-koylu.badge :variant="$badgeVariant">{{ $status->getLabel() }}</x-koylu.badge>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-gray-500 mb-1.5">
                        <span>{{ $driverRoute['stops_delivered'] }}/{{ $driverRoute['stops_total'] }} geleverd</span>
                        <span>{{ $driverRoute['progress_percent'] }}%</span>
                    </div>
                    <div class="koylu-progress mb-3">
                        <div class="koylu-progress-bar bg-koylu-green" style="width: {{ $driverRoute['progress_percent'] }}%"></div>
                    </div>

                    @if ($nextStop)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 px-3 py-2.5 mb-3">
                            <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold">Volgende stop</p>
                            <p class="text-xs font-medium text-gray-800 dark:text-gray-200 truncate mt-0.5">
                                {{ $nextStop->order?->customer?->company_name ?? '—' }}
                            </p>
                            <p class="text-[11px] text-gray-500 truncate">
                                {{ $nextStop->order?->customer?->city }}
                                @if ($driverRoute['remaining_stops'] > 0)
                                    · +{{ $driverRoute['remaining_stops'] }} daarna
                                @endif
                            </p>
                        </div>
                    @elseif ($status === RouteStatus::COMPLETED)
                        <p class="text-xs text-koylu-green mb-3">Route afgerond</p>
                    @endif

                    <a href="{{ $this->routeViewUrl($route->id) }}" class="koylu-link inline-flex items-center gap-0.5">
                        Route openen <x-heroicon-o-arrow-right class="w-3 h-3" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($driverRoutesPreview->isNotEmpty() && $driverRoutesOverflow > 0)
        <x-slot:footer>
            <span>+ {{ $driverRoutesOverflow }} {{ $driverRoutesOverflow === 1 ? 'andere route' : 'andere routes' }}</span>
            <a href="{{ $this->routesTodayUrl() }}" class="koylu-link">Alle routes →</a>
        </x-slot:footer>
    @endif
</x-koylu.card>
