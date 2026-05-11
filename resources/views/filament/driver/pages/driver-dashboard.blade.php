<x-filament-panels::page>
    {{-- Header --}}
    <div class="px-1 pb-2">
        <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">
            {{ now()->isoFormat('dddd D MMMM') }}
        </p>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-0.5">
            Goedemorgen, {{ auth()->user()->name }}
        </h1>
    </div>

    @if ($route)
        {{-- Actieve route kaart --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">

            {{-- Kaart header --}}
            <div class="bg-orange-500 px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-xl p-2">
                        <x-heroicon-o-map-pin class="w-5 h-5 text-white" />
                    </div>
                    <div>
                        <p class="text-orange-100 text-xs font-medium">Route van vandaag</p>
                        <p class="text-white font-semibold">{{ $route->route_date->format('d-m-Y') }}</p>
                    </div>
                </div>
                <span @class([
                    'px-3 py-1 rounded-full text-xs font-semibold',
                    'bg-white/20 text-white'          => $route->status === 'planned',
                    'bg-white text-orange-600'        => $route->status === 'in_progress',
                ])>
                    {{ $route->status === 'planned' ? 'Gepland' : 'Onderweg' }}
                </span>
            </div>

            <div class="p-5 space-y-5">
                {{-- Voertuig info --}}
                <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
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
                        <p class="text-2xl font-bold text-gray-800 dark:text-white">
                            {{ $route->routeStops->count() }}
                        </p>
                    </div>
                </div>

                {{-- Stops lijst --}}
                <div class="space-y-1">
                    @foreach ($route->routeStops as $stop)
                        <div class="flex items-center gap-3 py-2.5 border-b border-gray-100 dark:border-gray-800 last:border-0">
                            {{-- Stop nummer --}}
                            <div @class([
                                'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                'bg-green-100 text-green-700' => $stop->status === 'delivered',
                                'bg-orange-100 text-orange-700' => $stop->status === 'pending',
                            ])>
                                @if ($stop->status === 'delivered')
                                    <x-heroicon-s-check class="w-4 h-4" />
                                @else
                                    {{ $stop->stop_order }}
                                @endif
                            </div>

                            {{-- Klant info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-800 dark:text-white truncate">
                                    {{ $stop->order->customer->company_name }}
                                </p>
                                <p class="text-xs text-gray-400 truncate">
                                    {{ $stop->order->customer->city }}
                                </p>
                            </div>

                            {{-- Status badge --}}
                            <span @class([
                                'shrink-0 text-xs px-2 py-0.5 rounded-full font-medium',
                                'bg-green-100 text-green-700' => $stop->status === 'delivered',
                                'bg-gray-100 text-gray-500'   => $stop->status === 'pending',
                            ])>
                                {{ $stop->status === 'delivered' ? 'Geleverd' : 'Gepland' }}
                            </span>
                        </div>
                    @endforeach
                </div>

                {{-- Actie knoppen --}}
                <div class="space-y-2 pt-1">
                    @if ($route->status === \App\Enums\RouteStatus::PLANNED)
                        <button
                            wire:click="startLoading"
                            class="w-full flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold py-3.5 rounded-xl transition"
                        >
                            <x-heroicon-o-arrow-right-circle class="w-5 h-5" />
                            Start laden
                        </button>

                    @elseif ($route->status === \App\Enums\RouteStatus::IN_PROGRESS && !$route->loading_completed_at)
                        <div class="flex items-start gap-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-sm font-medium text-amber-700 dark:text-amber-400">Laadproces niet afgerond</p>
                                <p class="text-xs text-amber-500 mt-0.5">Rond eerst het laden af voordat je kunt leveren.</p>
                            </div>
                        </div>
                        <button
                            wire:click="continueLoading"
                            class="w-full flex items-center justify-center gap-2 bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-semibold py-3.5 rounded-xl transition"
                        >
                            <x-heroicon-o-cube class="w-5 h-5" />
                            Verder met laden
                        </button>

                    @elseif ($route->status === \App\Enums\RouteStatus::IN_PROGRESS && $route->loading_completed_at)
                        <div class="flex items-center gap-3 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 shrink-0" />
                            <div>
                                <p class="text-sm font-medium text-green-700 dark:text-green-400">Laden afgerond</p>
                                <p class="text-xs text-green-500 mt-0.5">Je kunt nu beginnen met leveren.</p>
                            </div>
                        </div>
                        <button
                            wire:click="continueDelivery"
                            class="w-full flex items-center justify-center gap-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white font-semibold py-3.5 rounded-xl transition"
                        >
                            <x-heroicon-o-truck class="w-5 h-5" />
                            Start leveren
                        </button>
                    @endif
                </div>
            </div>
        </div>

    @else
        {{-- Geen route vandaag --}}
        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm p-10 text-center">
            <div class="bg-gray-100 dark:bg-gray-800 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <x-heroicon-o-map class="w-8 h-8 text-gray-400" />
            </div>
            <p class="font-semibold text-gray-700 dark:text-white">Geen route voor vandaag</p>
            <p class="text-sm text-gray-400 mt-1">Neem contact op met de planner.</p>
        </div>
    @endif

    {{-- Toekomstige routes --}}
    @if ($futureRoutes->isNotEmpty())
        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <x-heroicon-o-calendar-days class="w-4 h-4 text-gray-400" />
                <h3 class="font-semibold text-sm text-gray-700 dark:text-white">Geplande routes</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($futureRoutes as $futureRoute)
                    <div class="flex items-center gap-4 px-5 py-4">
                        <div class="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-2.5 shrink-0">
                            <x-heroicon-o-calendar class="w-5 h-5 text-blue-500" />
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
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-600">
                            Gepland
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Afgeronde routes --}}
    @if ($pastRoutes->isNotEmpty())
        <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <x-heroicon-o-check-badge class="w-4 h-4 text-gray-400" />
                <h3 class="font-semibold text-sm text-gray-700 dark:text-white">Afgeronde routes</h3>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($pastRoutes as $pastRoute)
                    <div class="flex items-center gap-4 px-5 py-4">
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-xl p-2.5 shrink-0">
                            <x-heroicon-o-check-circle class="w-5 h-5 text-green-500" />
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
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-600">
                                Afgerond
                            </span>
                            @if ($pastRoute->completed_at)
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $pastRoute->completed_at->format('H:i') }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-panels::page>
