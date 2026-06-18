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
                TextColumn::make('invoice_number')
                    ->label('Factuurnummer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

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
