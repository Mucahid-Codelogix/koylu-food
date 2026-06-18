<x-filament-panels::page>
    <div class="koylu-page">

        @include('filament.admin.pages.dashboard._header')

        @include('filament.admin.pages.dashboard._alerts')

        @include('filament.admin.pages.dashboard._kpis')

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
            <div class="xl:col-span-7 space-y-6 min-w-0">
                @include('filament.admin.pages.dashboard._orders-preview')
                @include('filament.admin.pages.dashboard._invoices-preview')
            </div>

            <div class="xl:col-span-5 space-y-6 min-w-0 xl:sticky xl:top-4">
                @include('filament.admin.pages.dashboard._routes-preview')

                @if (! ($exact['is_connected'] ?? false))
                    <x-koylu.alert variant="warning">
                        <div>
                            <p class="text-sm font-semibold">Exact niet actief</p>
                            <p class="text-xs mt-1 opacity-80 leading-relaxed">
                                Facturen kunnen pas naar Exact zodra de koppeling is hersteld.
                            </p>
                            <a href="{{ $this->exactConnectionUrl() }}" class="koylu-link inline-block mt-2">Koppeling instellen →</a>
                        </div>
                    </x-koylu.alert>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
