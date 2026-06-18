<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Actions\InvoiceActionGroup;
use App\Models\Invoice;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_invoice_number')
                    ->label('Factuurnummer')
                    ->state(fn (Invoice $record): string => $record->displayInvoiceNumber())
                    ->searchable(query: function ($query, string $search): void {
                        $query->where('invoice_number', 'like', "%{$search}%")
                            ->orWhere('exact_document_number', 'like', "%{$search}%");
                    })
                    ->sortable(query: function ($query, string $direction): void {
                        $query->orderByRaw('COALESCE(exact_document_number, invoice_number) '.$direction);
                    })
                    ->weight('bold'),

                TextColumn::make('exact_sync_status')
                    ->label('Exact')
                    ->badge()
                    ->state(function (Invoice $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'Fout';
                        }

                        if ($record->isSyncedToExact()) {
                            return 'Geboekt';
                        }

                        return 'Concept';
                    })
                    ->color(function (Invoice $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'danger';
                        }

                        if ($record->isSyncedToExact()) {
                            return 'success';
                        }

                        return 'gray';
                    })
                    ->tooltip(fn (Invoice $record): ?string => $record->exact_sync_error),

                TextColumn::make('order.customer.company_name')
                    ->label('Klant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vervaldatum')
                    ->date('d-m-Y')
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->status !== InvoiceStatus::PAID && $record->due_date?->isPast()
                        ? 'danger' : null
                    ),

                TextColumn::make('total_amount')
                    ->label('Totaal')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                InvoiceActionGroup::make(),
            ]);
    }
}
