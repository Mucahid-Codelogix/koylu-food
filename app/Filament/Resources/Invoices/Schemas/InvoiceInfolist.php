<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\DeliveryDeviationSummary;
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
                            ->label('Intern nummer')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('exact_document_number')
                            ->label('Exact factuurnummer')
                            ->placeholder('Nog niet geboekt')
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
                            ->color(fn (Invoice $record) => $record->status !== InvoiceStatus::PAID && $record->due_date?->isPast()
                                ? 'danger' : null
                            ),

                        TextEntry::make('order.customer.company_name')
                            ->label('Klant'),

                        TextEntry::make('order.order_number')
                            ->label('Ordernummer')
                            ->copyable(),

                        TextEntry::make('sent_at')
                            ->label('Verzonden op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Nog niet verzonden'),
                    ]),

                Section::make('Bedragen')
                    ->schema([
                        TextEntry::make('subtotal_amount')
                            ->label('Subtotaal')
                            ->money('EUR'),

                        TextEntry::make('vat_breakdown')
                            ->label('BTW')
                            ->html()
                            ->state(fn (Invoice $record): string => $record->formattedVatBreakdown()),

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

                        TextEntry::make('exact_sync_status')
                            ->label('Exact sync')
                            ->badge()
                            ->state(function (Invoice $record): string {
                                if (filled($record->exact_sync_error)) {
                                    return 'Fout';
                                }

                                if ($record->isSyncedToExact()) {
                                    return 'Geboekt';
                                }

                                return 'Niet geboekt';
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
                    ]),

                Section::make('Leveringsafwijkingen')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (Invoice $record): bool => filled(
                        DeliveryDeviationSummary::html($record->order?->delivery)
                    ))
                    ->schema([
                        TextEntry::make('delivery_deviation_summary')
                            ->label('Chauffeur-notities')
                            ->html()
                            ->state(fn (Invoice $record): ?string => DeliveryDeviationSummary::html(
                                $record->order?->delivery
                            ))
                            ->columnSpanFull(),
                    ]),

                Section::make('Notities')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Opmerkingen')
                            ->placeholder('Geen opmerkingen')
                            ->columnSpanFull(),

                        TextEntry::make('order.notes')
                            ->label('Ordernotitie')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),

                Section::make('Systeem')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Aangemaakt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Bijgewerkt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
