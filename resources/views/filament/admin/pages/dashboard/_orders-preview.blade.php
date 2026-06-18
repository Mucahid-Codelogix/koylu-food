<x-koylu.card>
    <x-slot:header>
        <x-koylu.section-heading
            title="Te plannen"
            :subtitle="'Nieuwste orders · max. ' . \App\Services\AdminDashboardService::ORDERS_PREVIEW_LIMIT"
        />
        @if ($newOrdersCount > 0)
            <x-koylu.badge variant="primary">{{ $newOrdersCount }} totaal</x-koylu.badge>
        @endif
    </x-slot:header>
    @if ($newOrdersPreview->isEmpty())
        <x-koylu.empty title="Geen orders wachtend" text="Alles is ingepland voor vandaag">
            <x-slot:icon>
                <x-heroicon-o-check-circle class="w-full h-full text-koylu-green" />
            </x-slot:icon>
        </x-koylu.empty>
    @else
        <div class="overflow-x-auto">
            <table class="koylu-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th class="hidden sm:table-cell">Klant</th>
                        <th class="hidden md:table-cell">Tijd</th>
                        <th class="text-right">Bedrag</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($newOrdersPreview as $order)
                        <tr>
                            <td>
                                <a href="{{ $this->orderViewUrl($order->id) }}" class="font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $order->order_number }}
                                </a>
                                <p class="text-xs text-gray-500 sm:hidden truncate">{{ $order->customer?->company_name }}</p>
                            </td>
                            <td class="hidden sm:table-cell text-gray-600 dark:text-gray-300 max-w-[200px] truncate">
                                {{ $order->customer?->company_name ?? '—' }}
                            </td>
                            <td class="hidden md:table-cell text-gray-400 text-xs whitespace-nowrap">
                                {{ $order->created_at?->diffForHumans(short: true) }}
                            </td>
                            <td class="text-right font-semibold tabular-nums whitespace-nowrap">
                                €{{ number_format((float) $order->total_price, 2, ',', '.') }}
                            </td>
                            <td>
                                <a href="{{ $this->orderViewUrl($order->id) }}" class="text-gray-300 hover:text-primary-500 transition">
                                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($newOrdersPreview->isNotEmpty())
        <x-slot:footer>
            @if ($newOrdersOverflow > 0)
                <span>+ {{ $newOrdersOverflow }} {{ $newOrdersOverflow === 1 ? 'andere order' : 'andere orders' }}</span>
            @else
                <span>Alle open orders getoond</span>
            @endif
            <a href="{{ $this->ordersIndexUrl() }}" class="koylu-link">Alle orders →</a>
        </x-slot:footer>
    @endif
</x-koylu.card>
