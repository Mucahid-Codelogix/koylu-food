<?php

namespace App\Filament\Customer\Pages;

use App\Models\Invoice;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class Invoices extends Page
{
    protected string $view = 'filament.customer.pages.invoices';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Facturen';
    protected static ?string $title = 'Mijn Facturen';

    public Collection $invoices;

    public function mount(): void
    {
        $this->invoices = Invoice::with(['order.customer', 'order.items'])
            ->whereHas('order', function ($q) {
                $q->where('customer_id', auth()->user()->customer_id);
            })
            ->orderByDesc('invoice_date')
            ->get();
    }
}
