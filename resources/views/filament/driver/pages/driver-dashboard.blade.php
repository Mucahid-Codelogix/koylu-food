<x-filament-panels::page>
    <div class="koylu-page">
        <header class="koylu-page-header">
            <div>
                <p class="koylu-page-meta">{{ now()->isoFormat('dddd D MMMM') }}</p>
                <h1 class="koylu-page-title mt-0.5">Goedemorgen, {{ auth()->user()->name }}</h1>
            </div>
        </header>

        @if ($route)
            <x-koylu.card>
                <div class="koylu-card-hero">
                    <div class="flex items-center gap-3">
                        <div class="koylu-card-hero-icon">
                            <x-heroicon-o-map-pin class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <p class="koylu-card-hero-label">Route van vandaag</p>
                            <p class="koylu-card-hero-title">{{ $route->route_date->format('d-m-Y') }}</p>
                        </div>
                    </div>
                    <x-koylu.badge :variant="$route->status === \App\Enums\RouteStatus::IN_PROGRESS ? 'primary' : 'neutral'">
                        {{ $route->status === \App\Enums\RouteStatus::PLANNED ? 'Gepland' : 'Onderweg' }}
                    </x-koylu.badge>
                </div>

                <div class="koylu-card-body space-y-5">
                    <div class="koylu-info-box-neutral">
                        <div class="bg-gray-200 dark:bg-gray-700 rounded-lg p-2">
                            <x-heroicon-o-truck class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                        </div>
                        <div>
                            <p class="text-xs text-gray-400">Voertuig</p>
                            <p class="font-semibold text-sm text-gray-800 dark:text-white">
                                {{ $route->vehicle->brand }} {{ $route->vehicle->model }}
                            </p>
                            <p class="text-xs text-gray-400 font-mono">{{ $route->vehicle->license_plate }}</p>
                        </div>
                        <div class="ml-auto text-right">
                            <p class="text-xs text-gray-400">Stops</p>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white tabular-nums">
                                {{ $route->routeStops->count() }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1">
                        @foreach ($route->routeStops as $stop)
                            @php
                                $dotClass = match ($stop->status) {
                                    \App\Enums\RouteStopStatus::DELIVERED => 'koylu-stop-dot-delivered',
                                    \App\Enums\RouteStopStatus::SKIPPED => 'koylu-stop-dot-skipped',
                                    default => 'koylu-stop-dot-pending',
                                };
                                $badgeVariant = match ($stop->status) {
                                    \App\Enums\RouteStopStatus::DELIVERED => 'success',
                                    \App\Enums\RouteStopStatus::SKIPPED => 'warning',
                                    default => 'neutral',
                                };
                            @endphp
                            <div class="flex items-center gap-3 py-2.5 border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <div class="{{ $dotClass }}">
                                    @if ($stop->status === \App\Enums\RouteStopStatus::DELIVERED)
                                        <x-heroicon-s-check class="w-4 h-4" />
                                    @elseif ($stop->status === \App\Enums\RouteStopStatus::SKIPPED)
                                        <x-heroicon-o-forward class="w-4 h-4" />
                                    @else
                                        {{ $stop->stop_order }}
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm text-gray-800 dark:text-white truncate">
                                        {{ $stop->order->customer->company_name }}
                                    </p>
                                    <p class="text-xs text-gray-400 truncate">
                                        {{ $stop->order->customer->city }}
                                    </p>
                                </div>

                                <x-koylu.badge :variant="$badgeVariant" class="shrink-0">
                                    {{ $stop->status->getLabel() }}
                                </x-koylu.badge>
                            </div>
                        @endforeach
                    </div>

                    <div class="space-y-2 pt-1">
                        @if ($route->status === \App\Enums\RouteStatus::PLANNED)
                            <button wire:click="startLoading" class="koylu-btn-primary">
                                <x-heroicon-o-arrow-right-circle class="w-5 h-5" />
                                Start laden
                            </button>

                        @elseif ($route->status === \App\Enums\RouteStatus::IN_PROGRESS && ! $route->loading_completed_at)
                            <div class="koylu-info-box-warning">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">Laadproces niet afgerond</p>
                                    <p class="text-xs text-amber-500 mt-0.5">Rond eerst het laden af voordat je kunt leveren.</p>
                                </div>
                            </div>
                            <button wire:click="continueLoading" class="koylu-btn-primary">
                                <x-heroicon-o-cube class="w-5 h-5" />
                                Verder met laden
                            </button>

                        @elseif ($route->status === \App\Enums\RouteStatus::IN_PROGRESS && $route->loading_completed_at)
                            <div class="koylu-info-box-success">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-koylu-green shrink-0" />
                                <div>
                                    <p class="text-sm font-medium text-koylu-green dark:text-koylu-green-50">Laden afgerond</p>
                                    <p class="text-xs text-koylu-green/80 mt-0.5">Je kunt nu beginnen met leveren.</p>
                                </div>
                            </div>
                            <button wire:click="continueDelivery" class="koylu-btn-primary">
                                <x-heroicon-o-truck class="w-5 h-5" />
                                Start leveren
                            </button>
                        @endif
                    </div>
                </div>
            </x-koylu.card>

        @else
            <x-koylu.empty title="Geen route voor vandaag" text="Neem contact op met de planner">
                <x-slot:icon>
                    <x-heroicon-o-map class="w-full h-full" />
                </x-slot:icon>
            </x-koylu.empty>
        @endif

        @if ($futureRoutes->isNotEmpty())
            <x-koylu.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-calendar-days class="w-4 h-4 text-gray-400" />
                        <x-koylu.section-heading title="Geplande routes" />
                    </div>
                </x-slot:header>

                <ul class="divide-y divide-gray-50 dark:divide-gray-800/80">
                    @foreach ($futureRoutes as $futureRoute)
                        <li class="koylu-list-item">
                            <div class="bg-primary-50 dark:bg-primary-500/10 rounded-xl p-2.5 shrink-0">
                                <x-heroicon-o-calendar class="w-5 h-5 text-primary-500" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-800 dark:text-white">
                                    {{ $futureRoute->route_date->isoFormat('dddd D MMMM') }}
                                </p>
                                <p class="text-xs text-gray-400 truncate">
                                    {{ $futureRoute->vehicle->brand }} {{ $futureRoute->vehicle->model }}
                                    · {{ $futureRoute->routeStops->count() }} stops
                                </p>
                            </div>
                            <x-koylu.badge variant="primary">Gepland</x-koylu.badge>
                        </li>
                    @endforeach
                </ul>
            </x-koylu.card>
        @endif

        @if ($pastRoutes->isNotEmpty())
            <x-koylu.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-check-badge class="w-4 h-4 text-gray-400" />
                        <x-koylu.section-heading title="Afgeronde routes" />
                    </div>
                </x-slot:header>

                <ul class="divide-y divide-gray-50 dark:divide-gray-800/80">
                    @foreach ($pastRoutes as $pastRoute)
                        <li class="koylu-list-item">
                            <div class="bg-koylu-green-50 dark:bg-koylu-green/10 rounded-xl p-2.5 shrink-0">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-koylu-green" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-800 dark:text-white">
                                    {{ $pastRoute->route_date->format('d-m-Y') }}
                                </p>
                                <p class="text-xs text-gray-400 truncate">
                                    {{ $pastRoute->vehicle->brand }} {{ $pastRoute->vehicle->model }}
                                    · {{ $pastRoute->routeStops->count() }} stops
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <x-koylu.badge variant="success">Afgerond</x-koylu.badge>
                                @if ($pastRoute->completed_at)
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ $pastRoute->completed_at->format('H:i') }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-koylu.card>
        @endif
    </div>
</x-filament-panels::page>
