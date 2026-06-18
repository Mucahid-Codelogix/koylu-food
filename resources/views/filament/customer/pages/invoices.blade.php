<x-filament-panels::page>
    <div class="koylu-page">
        @if ($invoices->isEmpty())
            <x-koylu.empty title="Geen facturen gevonden" text="Facturen verschijnen hier na levering van uw bestelling">
                <x-slot:icon>
                    <x-heroicon-o-document-text class="w-full h-full text-primary-300" />
                </x-slot:icon>
            </x-koylu.empty>
        @else
            @php
                $total       = $invoices->count();
                $sent        = $invoices->where('status', 'sent')->count();
                $paid        = $invoices->where('status', 'paid')->count();
                $totalAmount = $invoices->whereIn('status', ['sent', 'paid'])->sum('total_amount');
            @endphp

            <div class="koylu-kpi-grid">
                <x-koylu.kpi label="Totaal" :value="$total" meta="facturen" />
                <x-koylu.kpi label="Openstaand" :value="$sent" meta="te betalen" />
                <x-koylu.kpi label="Betaald" :value="$paid" meta="facturen" />
                <x-koylu.kpi
                    label="Totaalbedrag"
                    :value="'€' . number_format($totalAmount, 2, ',', '.')"
                    meta="gefactureerd"
                />
            </div>

            <x-koylu.card>
                <x-slot:header>
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                        <x-koylu.section-heading title="Factuuroverzicht" />
                    </div>
                </x-slot:header>

                <div class="hidden sm:block overflow-x-auto">
                    <table class="koylu-table">
                        <thead>
                            <tr>
                                <th>Factuurnummer</th>
                                <th>Datum</th>
                                <th>Vervaldatum</th>
                                <th>Bedrag</th>
                                <th>Status</th>
                                <th class="text-right">Downloads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td class="font-semibold text-gray-900 dark:text-white">
                                        {{ $invoice->invoice_number }}
                                    </td>
                                    <td class="text-gray-600 dark:text-gray-300">
                                        {{ $invoice->invoice_date?->format('d-m-Y') ?? '-' }}
                                    </td>
                                    <td>
                                        @if ($invoice->due_date)
                                            <span @class([
                                                'font-medium',
                                                'text-red-500' => $invoice->status !== 'paid' && $invoice->due_date->isPast(),
                                                'text-gray-600 dark:text-gray-300' => ! ($invoice->status !== 'paid' && $invoice->due_date->isPast()),
                                            ])>
                                                {{ $invoice->due_date->format('d-m-Y') }}
                                            </span>
                                            @if ($invoice->status !== 'paid' && $invoice->due_date->isPast())
                                                <span class="block text-xs text-red-400">Verlopen</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="font-semibold tabular-nums">
                                        €{{ number_format($invoice->total_amount, 2, ',', '.') }}
                                    </td>
                                    <td>
                                        @php
                                            $statusVariant = match ($invoice->status) {
                                                'paid' => 'success',
                                                'sent' => 'primary',
                                                default => 'neutral',
                                            };
                                            $statusLabel = match ($invoice->status) {
                                                'concept' => 'In behandeling',
                                                'sent' => 'Openstaand',
                                                'paid' => 'Betaald',
                                                default => $invoice->status,
                                            };
                                        @endphp
                                        <x-koylu.badge :variant="$statusVariant">{{ $statusLabel }}</x-koylu.badge>
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($invoice->pdf_path)
                                                <a
                                                    href="{{ route('invoice.pdf', $invoice) }}"
                                                    target="_blank"
                                                    class="koylu-link inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-50 hover:bg-primary-100 dark:bg-primary-500/10"
                                                >
                                                    <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                                                    PDF
                                                </a>
                                            @endif

                                            @if ($invoice->ubl_path)
                                                <a
                                                    href="{{ route('invoice.ubl', $invoice) }}"
                                                    target="_blank"
                                                    class="koylu-link inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300"
                                                >
                                                    <x-heroicon-o-code-bracket class="w-3.5 h-3.5" />
                                                    UBL
                                                </a>
                                            @endif

                                            @if (! $invoice->pdf_path && ! $invoice->ubl_path)
                                                <span class="text-xs text-gray-400">Nog niet beschikbaar</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="sm:hidden divide-y divide-gray-50 dark:divide-gray-800/80">
                    @foreach ($invoices as $invoice)
                        @php
                            $statusVariant = match ($invoice->status) {
                                'paid' => 'success',
                                'sent' => 'primary',
                                default => 'neutral',
                            };
                            $statusLabel = match ($invoice->status) {
                                'concept' => 'In behandeling',
                                'sent' => 'Openstaand',
                                'paid' => 'Betaald',
                                default => $invoice->status,
                            };
                        @endphp
                        <div class="p-4 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ $invoice->invoice_date?->format('d-m-Y') ?? '-' }}
                                    </p>
                                </div>
                                <x-koylu.badge :variant="$statusVariant">{{ $statusLabel }}</x-koylu.badge>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-lg font-bold text-gray-900 dark:text-white tabular-nums">
                                        €{{ number_format($invoice->total_amount, 2, ',', '.') }}
                                    </p>
                                    @if ($invoice->due_date)
                                        <p @class([
                                            'text-xs mt-0.5',
                                            'text-red-400' => $invoice->status !== 'paid' && $invoice->due_date->isPast(),
                                            'text-gray-400' => ! ($invoice->status !== 'paid' && $invoice->due_date->isPast()),
                                        ])>
                                            Vervalt {{ $invoice->due_date->format('d-m-Y') }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2">
                                    @if ($invoice->pdf_path)
                                        <a
                                            href="{{ route('invoice.pdf', $invoice) }}"
                                            target="_blank"
                                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-primary-500 hover:bg-primary-600 text-white text-xs font-semibold transition"
                                        >
                                            <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                            PDF
                                        </a>
                                    @endif

                                    @if ($invoice->ubl_path)
                                        <a
                                            href="{{ route('invoice.ubl', $invoice) }}"
                                            target="_blank"
                                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs font-semibold transition"
                                        >
                                            <x-heroicon-o-code-bracket class="w-4 h-4" />
                                            UBL
                                        </a>
                                    @endif

                                    @if (! $invoice->pdf_path && ! $invoice->ubl_path)
                                        <span class="text-xs text-gray-400">Nog niet beschikbaar</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-koylu.card>
        @endif
    </div>
</x-filament-panels::page>
