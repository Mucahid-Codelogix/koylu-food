<x-filament-panels::page>
    <div class="koylu-page">
        <x-koylu.card>
            <x-slot:header>
                <x-koylu.section-heading
                    title="Verbindingsstatus"
                    subtitle="Koppel de app met Exact Online via OAuth. Tokens worden versleuteld opgeslagen."
                />
                <x-koylu.badge :variant="$isConnected ? 'success' : 'neutral'">
                    {{ $isConnected ? 'Gekoppeld' : 'Niet gekoppeld' }}
                </x-koylu.badge>
            </x-slot:header>

            <div class="koylu-card-body">
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4 sm:col-span-2 xl:col-span-4">
                        <dt class="koylu-kpi-label">Callback URI (Exact App Center)</dt>
                        <dd class="mt-1 text-sm font-mono font-semibold text-gray-900 dark:text-white break-all">{{ $redirectUri ?? '—' }}</dd>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Deze URL moet <strong>exact</strong> geregistreerd staan bij je Exact-app (App Center → Callback URL).
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
                        <dt class="koylu-kpi-label">Administratie (config)</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $configuredDivision ?? '—' }}</dd>
                    </div>

                    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
                        <dt class="koylu-kpi-label">Administratie (token)</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $division ?? '—' }}</dd>
                        @if ($isConnected && blank($division))
                            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Klik op <strong>Verbinding testen</strong> om het division-nummer op te halen.
                            </p>
                        @endif
                    </div>

                    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
                        <dt class="koylu-kpi-label">Token verloopt</dt>
                        <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $expiresAt ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </x-koylu.card>

        <div class="flex flex-wrap gap-3">
            @if (! $isConnected)
                <x-filament::button
                    tag="a"
                    href="{{ route('exact.oauth.redirect') }}"
                    color="primary"
                    icon="heroicon-o-link"
                >
                    Verbinden met Exact
                </x-filament::button>
            @else
                <x-filament::button
                    wire:click="importProductsFromExact"
                    wire:confirm="Alle verkoopartikelen uit Exact importeren of bijwerken in de app?"
                    color="primary"
                    icon="heroicon-o-arrow-down-tray"
                >
                    Importeer artikelen uit Exact
                </x-filament::button>

                <x-filament::button
                    wire:click="importCustomersFromExact"
                    wire:confirm="Alle debiteuren uit Exact importeren of bijwerken in de app?"
                    color="primary"
                    icon="heroicon-o-arrow-down-tray"
                >
                    Importeer klanten uit Exact
                </x-filament::button>

                <x-filament::button
                    wire:click="importSuppliersFromExact"
                    wire:confirm="Alle crediteuren uit Exact importeren of bijwerken in de app?"
                    color="primary"
                    icon="heroicon-o-arrow-down-tray"
                >
                    Importeer leveranciers uit Exact
                </x-filament::button>

                <x-filament::button
                    wire:click="testConnection"
                    color="gray"
                    icon="heroicon-o-signal"
                >
                    Verbinding testen
                </x-filament::button>

                <x-filament::button
                    wire:click="disconnect"
                    wire:confirm="Weet je zeker dat je de Exact-koppeling wilt verbreken?"
                    color="danger"
                    icon="heroicon-o-x-circle"
                >
                    Koppeling verbreken
                </x-filament::button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
