@if ($conceptInvoicesCount > 0)
    <x-koylu.card>
        <x-slot:header>
            <x-koylu.section-heading
                title="Te boeken in Exact"
                subtitle="Conceptfacturen wachten op goedkeuring"
            />
            <x-koylu.badge variant="primary">{{ $conceptInvoicesCount }}</x-koylu.badge>
        </x-slot:header>

        <ul class="divide-y divide-gray-50 dark:divide-gray-800/80">
            @foreach ($conceptInvoicesPreview as $invoice)
                <li>
                    <a href="{{ $this->invoiceViewUrl($invoice->id) }}" class="koylu-list-item">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate hover:text-primary-600">
                                {{ $invoice->displayInvoiceNumber() }}
                            </p>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $invoice->order?->customer?->company_name ?? 'Onbekende klant' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold tabular-nums">€{{ number_format((float) $invoice->total_amount, 2, ',', '.') }}</p>
                            <p class="text-[10px] text-gray-400">{{ $invoice->created_at?->format('d-m') }}</p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        <x-slot:footer>
            <span>
                @if ($conceptInvoicesOverflow > 0)
                    + {{ $conceptInvoicesOverflow }} meer
                @else
                    Preview getoond
                @endif
            </span>
            <a href="{{ $this->conceptInvoicesUrl() }}" class="koylu-link">Alle facturen →</a>
        </x-slot:footer>
    </x-koylu.card>
@endif
