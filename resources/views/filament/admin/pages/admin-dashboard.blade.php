@php
    use App\Enums\RouteStatus;
    $pendingStops = $totalStopsToday - $deliveredStopsToday;
@endphp

<x-filament-panels::page>
    <div class="space-y-8">

        {{-- Header --}}
        <div>
            <p class="text-xs font-semibold tracking-widest text-gray-400 dark:text-gray-500 uppercase">
                {{ now()->isoFormat('dddd D MMMM YYYY') }}
            </p>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                Welkom terug, {{ auth()->user()->name }}
            </h1>
        </div>

        {{-- Statistieken --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nieuwe orders</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $newOrdersCount }}</p>
                        <p class="text-xs text-gray-400 mt-1">Status: geplaatst</p>
                    </div>
                    <div class="rounded-xl bg-amber-100 dark:bg-amber-500/20 p-3">
                        <x-heroicon-o-shopping-bag class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Leveringen vandaag</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white mt-1">{{ $totalStopsToday }}</p>
                        <p class="text-xs text-gray-400 mt-1">Totaal aantal stops</p>
                    </div>
                    <div class="rounded-xl bg-blue-100 dark:bg-blue-500/20 p-3">
                        <x-heroicon-o-map-pin class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Geleverd</p>
                        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ $deliveredStopsToday }}</p>
                        <p class="text-xs text-gray-400 mt-1">Stops afgerond</p>
                    </div>
                    <div class="rounded-xl bg-emerald-100 dark:bg-emerald-500/20 p-3">
                        <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Nog te leveren</p>
                        <p class="text-3xl font-bold text-primary-600 dark:text-primary-400 mt-1">{{ $pendingStops }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ $driverRoutes->count() }} chauffeur(s) actief</p>
                    </div>
                    <div class="rounded-xl bg-primary-100 dark:bg-primary-500/20 p-3">
                        <x-heroicon-o-truck class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Nieuwe orders --}}
        <section class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-amber-500 p-2">
                            <x-heroicon-o-shopping-bag class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Nieuwe orders</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Wachten op inplanning</p>
                        </div>
                    </div>
                    <a
                        href="{{ $this->ordersIndexUrl() }}"
                        class="text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 whitespace-nowrap"
                    >
                        Alle orders →
                    </a>
                </div>

                <div class="flex-1 divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($newOrders as $order)
                        <a
                            href="{{ $this->orderViewUrl($order->id) }}"
                            class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition group"
                        >
                            <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center shrink-0">
                                <x-heroicon-o-document-text class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition truncate">
                                    {{ $order->order_number }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                    {{ $order->customer?->company_name ?? 'Onbekende klant' }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $order->created_at?->format('d-m-Y H:i') }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-bold text-sm text-gray-900 dark:text-white">
                                    €{{ number_format((float) $order->total_price, 2, ',', '.') }}
                                </p>
                                <span class="inline-flex mt-1 text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                                    Geplaatst
                                </span>
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-12 text-center">
                            <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                                <x-heroicon-o-inbox class="w-6 h-6 text-gray-400" />
                            </div>
                            <p class="font-medium text-gray-600 dark:text-gray-300 text-sm">Geen nieuwe orders</p>
                            <p class="text-xs text-gray-400 mt-1">Alle orders zijn verwerkt</p>
                        </div>
                    @endforelse
                </div>
        </section>

        {{-- Leveringen vandaag per chauffeur --}}
        <section class="space-y-4">
                <div class="flex items-center justify-between gap-3 px-1">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-blue-500 p-2">
                            <x-heroicon-o-truck class="w-5 h-5 text-white" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Leveringen vandaag</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Per chauffeur</p>
                        </div>
                    </div>
                    <a
                        href="{{ $this->routesTodayUrl() }}"
                        class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap"
                    >
                        Routes →
                    </a>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                @forelse ($driverRoutes as $driverRoute)
                    @php
                        $route = $driverRoute['route'];
                        $status = $route->status;
                    @endphp

                    <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-sm overflow-hidden">
                        {{-- Chauffeur header --}}
                        <div @class([
                            'px-5 py-4 flex items-center justify-between gap-3',
                            'bg-gray-50 dark:bg-gray-800/50' => $status !== RouteStatus::IN_PROGRESS,
                            'bg-gradient-to-r from-blue-500 to-blue-600' => $status === RouteStatus::IN_PROGRESS,
                        ])>
                            <div class="flex items-center gap-3 min-w-0">
                                <div @class([
                                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0 font-bold text-sm',
                                    'bg-white/20 text-white' => $status === RouteStatus::IN_PROGRESS,
                                    'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300' => $status !== RouteStatus::IN_PROGRESS,
                                ])>
                                    {{ strtoupper(substr($driverRoute['driver_name'], 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p @class([
                                        'font-semibold truncate',
                                        'text-white' => $status === RouteStatus::IN_PROGRESS,
                                        'text-gray-900 dark:text-white' => $status !== RouteStatus::IN_PROGRESS,
                                    ])>
                                        {{ $driverRoute['driver_name'] }}
                                    </p>
                                    @if ($route->vehicle)
                                        <p @class([
                                            'text-xs truncate',
                                            'text-blue-100' => $status === RouteStatus::IN_PROGRESS,
                                            'text-gray-500 dark:text-gray-400' => $status !== RouteStatus::IN_PROGRESS,
                                        ])>
                                            {{ $route->vehicle->brand }} {{ $route->vehicle->model }}
                                            · {{ $route->vehicle->license_plate }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                            <span @class([
                                'shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full',
                                'bg-white/20 text-white' => $status === RouteStatus::IN_PROGRESS,
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => $status === RouteStatus::PLANNED,
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $status === RouteStatus::COMPLETED,
                            ])>
                                {{ $status->getLabel() }}
                            </span>
                        </div>

                        {{-- Voortgang --}}
                        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1.5">
                                <span>{{ $driverRoute['stops_delivered'] }} / {{ $driverRoute['stops_total'] }} geleverd</span>
                                <span>{{ $driverRoute['progress_percent'] }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                    style="width: {{ $driverRoute['progress_percent'] }}%"
                                ></div>
                            </div>
                        </div>

                        {{-- Stops --}}
                        <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-64 overflow-y-auto">
                            @foreach ($route->routeStops as $stop)
                                <div class="flex items-center gap-3 px-5 py-3">
                                    <div @class([
                                        'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' => $stop->status === \App\Enums\RouteStopStatus::DELIVERED,
                                        'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => $stop->status !== \App\Enums\RouteStopStatus::DELIVERED,
                                    ])>
                                        @if ($stop->status === \App\Enums\RouteStopStatus::DELIVERED)
                                            <x-heroicon-s-check class="w-4 h-4" />
                                        @else
                                            {{ $stop->stop_order }}
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-800 dark:text-white truncate">
                                            {{ $stop->order?->customer?->company_name ?? '—' }}
                                        </p>
                                        <p class="text-xs text-gray-400 truncate">
                                            {{ $stop->order?->customer?->city }}
                                            · {{ $stop->order?->order_number }}
                                        </p>
                                    </div>
                                    <span @class([
                                        'text-xs px-2 py-0.5 rounded-full font-medium shrink-0',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400' => $stop->status === \App\Enums\RouteStopStatus::DELIVERED,
                                        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $stop->status === \App\Enums\RouteStopStatus::PENDING,
                                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' => $stop->status === \App\Enums\RouteStopStatus::SKIPPED,
                                    ])>
                                        {{ $stop->status->getLabel() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/30 border-t border-gray-100 dark:border-gray-800">
                            <a
                                href="{{ $this->routeViewUrl($route->id) }}"
                                class="text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 flex items-center gap-1"
                            >
                                Route bekijken
                                <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2 2xl:col-span-3 rounded-2xl bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-700 px-5 py-12 text-center">
                        <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-3">
                            <x-heroicon-o-truck class="w-6 h-6 text-gray-400" />
                        </div>
                        <p class="font-medium text-gray-600 dark:text-gray-300 text-sm">Geen routes vandaag</p>
                        <p class="text-xs text-gray-400 mt-1">Plan routes in voor de chauffeurs</p>
                        <a
                            href="{{ $this->routesTodayUrl() }}"
                            class="inline-flex mt-4 text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400"
                        >
                            Naar routes →
                        </a>
                    </div>
                @endforelse
                </div>
        </section>
    </div>
</x-filament-panels::page>
