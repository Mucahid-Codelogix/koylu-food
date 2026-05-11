<x-filament-panels::page>

    {{-- Leeg --}}
    @if ($invoices->isEmpty())
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-12 text-center">
            <div class="bg-orange-50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                <x-heroicon-o-document-text class="w-8 h-8 text-orange-300" />
            </div>
            <p class="font-semibold text-gray-700">Geen facturen gevonden</p>
            <p class="text-sm text-gray-400 mt-1">Facturen verschijnen hier na levering van uw bestelling.</p>
        </div>

    @else

        {{-- Statistieken --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @php
                $total     = $invoices->count();
                $concept   = $invoices->where('status', 'concept')->count();
                $sent      = $invoices->where('status', 'sent')->count();
                $paid      = $invoices->where('status', 'paid')->count();
                $totalAmount = $invoices->whereIn('status', ['sent', 'paid'])->sum('total_amount');
            @endphp

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Totaal</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $total }}</p>
                <p class="text-xs text-gray-400 mt-0.5">facturen</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Openstaand</p>
                <p class="text-2xl font-bold text-amber-500 mt-1">{{ $sent }}</p>
                <p class="text-xs text-gray-400 mt-0.5">te betalen</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Betaald</p>
                <p class="text-2xl font-bold text-green-500 mt-1">{{ $paid }}</p>
                <p class="text-xs text-gray-400 mt-0.5">facturen</p>
            </div>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Totaalbedrag</p>
                <p class="text-2xl font-bold text-orange-500 mt-1">€{{ number_format($totalAmount, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-400 mt-0.5">gefactureerd</p>
            </div>
        </div>

        {{-- Factuurlijst --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <x-heroicon-o-document-text class="w-4 h-4 text-gray-400" />
                <h3 class="font-semibold text-sm text-gray-700">Factuuroverzicht</h3>
            </div>

            {{-- Desktop tabel --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Factuurnummer</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Datum</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Vervaldatum</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Bedrag</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Downloads</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($invoices as $invoice)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-4 font-semibold text-gray-900">
                                {{ $invoice->invoice_number }}
                            </td>
                            <td class="px-5 py-4 text-gray-600">
                                {{ $invoice->invoice_date?->format('d-m-Y') ?? '-' }}
                            </td>
                            <td class="px-5 py-4">
                                @if ($invoice->due_date)
                                    <span @class([
                                            'font-medium',
                                            'text-red-500' => $invoice->status !== 'paid' && $invoice->due_date->isPast(),
                                            'text-gray-600' => !($invoice->status !== 'paid' && $invoice->due_date->isPast()),
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
                            <td class="px-5 py-4 font-semibold text-gray-900">
                                €{{ number_format($invoice->total_amount, 2, ',', '.') }}
                            </td>
                            <td class="px-5 py-4">
                                    <span @class([
                                        'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                                        'bg-gray-100 text-gray-500'   => $invoice->status === 'concept',
                                        'bg-amber-100 text-amber-700' => $invoice->status === 'sent',
                                        'bg-green-100 text-green-700' => $invoice->status === 'paid',
                                    ])>
                                        @if ($invoice->status === 'concept')
                                            <x-heroicon-s-clock class="w-3 h-3" />
                                            In behandeling
                                        @elseif ($invoice->status === 'sent')
                                            <x-heroicon-s-exclamation-circle class="w-3 h-3" />
                                            Openstaand
                                        @elseif ($invoice->status === 'paid')
                                            <x-heroicon-s-check-circle class="w-3 h-3" />
                                            Betaald
                                        @endif
                                    </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($invoice->pdf_path)
                                        <a
                                        href="{{ route('invoice.pdf', $invoice) }}"
                                        target="_blank"
                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-50 hover:bg-orange-100 text-orange-600 text-xs font-semibold transition"
                                        >
                                        <x-heroicon-o-document-arrow-down class="w-3.5 h-3.5" />
                                        PDF
                                        </a>
                                    @endif

                                    @if ($invoice->ubl_path)
                                        <a
                                        href="{{ route('invoice.ubl', $invoice) }}"
                                        target="_blank"
                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 hover:bg-gray-100 text-gray-600 text-xs font-semibold transition"
                                        >
                                        <x-heroicon-o-code-bracket class="w-3.5 h-3.5" />
                                        UBL
                                        </a>
                                    @endif

                                    @if (!$invoice->pdf_path && !$invoice->ubl_path)
                                        <span class="text-xs text-gray-400">Nog niet beschikbaar</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobiel kaarten --}}
            <div class="sm:hidden divide-y divide-gray-100">
                @foreach ($invoices as $invoice)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $invoice->invoice_number }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $invoice->invoice_date?->format('d-m-Y') ?? '-' }}
                                </p>
                            </div>
                            <span @class([
                                'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                                'bg-gray-100 text-gray-500'   => $invoice->status === 'concept',
                                'bg-amber-100 text-amber-700' => $invoice->status === 'sent',
                                'bg-green-100 text-green-700' => $invoice->status === 'paid',
                            ])>
                                @if ($invoice->status === 'concept')
                                    In behandeling
                                @elseif ($invoice->status === 'sent')
                                    Openstaand
                                @elseif ($invoice->status === 'paid')
                                    Betaald
                                @endif
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-lg font-bold text-gray-900">
                                    €{{ number_format($invoice->total_amount, 2, ',', '.') }}
                                </p>
                                @if ($invoice->due_date)
                                    <p @class([
                                        'text-xs mt-0.5',
                                        'text-red-400' => $invoice->status !== 'paid' && $invoice->due_date->isPast(),
                                        'text-gray-400' => !($invoice->status !== 'paid' && $invoice->due_date->isPast()),
                                    ])>
                                        Vervalt {{ $invoice->due_date->format('d-m-Y') }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($invoice->pdf_path)

                                    href="{{ route('invoice.pdf', $invoice) }}"
                                    target="_blank"
                                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold transition"
                                    >
                                    <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                    PDF
                                    </a>
                                @endif

                                @if ($invoice->ubl_path)

                                    href="{{ route('invoice.ubl', $invoice) }}"
                                    target="_blank"
                                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold transition"
                                    >
                                    <x-heroicon-o-code-bracket class="w-4 h-4" />
                                    UBL
                                    </a>
                                @endif

                                @if (!$invoice->pdf_path && !$invoice->ubl_path)
                                    <span class="text-xs text-gray-400">Nog niet beschikbaar</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</x-filament-panels::page>
