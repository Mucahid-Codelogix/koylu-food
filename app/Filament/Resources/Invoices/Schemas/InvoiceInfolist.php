<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factuurgegevens')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('invoice_number')
                            ->label('Factuurnummer')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('invoice_date')
                            ->label('Factuurdatum')
                            ->date('d-m-Y')
                            ->placeholder('-'),

                        TextEntry::make('due_date')
                            ->label('Vervaldatum')
                            ->date('d-m-Y')
                            ->placeholder('-')
                            ->color(fn ($record) => $record->status !== 'paid' && $record->due_date?->isPast()
                                ? 'danger' : null
                            ),

                        TextEntry::make('order.customer.company_name')
                            ->label('Klant'),

                        TextEntry::make('sent_at')
                            ->label('Verzonden op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Nog niet verzonden'),
                    ]),

                Section::make('Bedragen')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subtotal_amount')
                            ->label('Subtotaal')
                            ->money('EUR'),

                        TextEntry::make('vat_amount')
                            ->label('BTW (21%)')
                            ->money('EUR'),

                        TextEntry::make('total_amount')
                            ->label('Totaal')
                            ->money('EUR')
                            ->weight('bold')
                            ->size('lg'),
                    ]),

                Section::make('Bestanden')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pdf_path')
                            ->label('PDF')
                            ->placeholder('Nog niet aangemaakt')
                            ->formatStateUsing(fn ($state) => $state ? 'Beschikbaar' : '-')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        TextEntry::make('ubl_path')
                            ->label('UBL')
                            ->placeholder('Nog niet aangemaakt')
                            ->formatStateUsing(fn ($state) => $state ? 'Beschikbaar' : '-')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray'),

                        TextEntry::make('exact_invoice_id')
                            ->label('Exact factuur ID')
                            ->placeholder('-'),
                    ]),

                Section::make('Notities')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Opmerkingen')
                            ->placeholder('Geen opmerkingen')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
