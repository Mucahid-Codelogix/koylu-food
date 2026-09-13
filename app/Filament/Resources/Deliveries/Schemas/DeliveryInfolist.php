<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Models\Delivery;
use App\Services\DeliveryNoteService;
use App\Support\DeliveryDeviationSummary;
use App\Support\UploadStorage;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Levering')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Bestelling')
                            ->placeholder('-'),

                        TextEntry::make('order.customer.company_name')
                            ->label('Klant')
                            ->placeholder('-'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('delivered_at')
                            ->label('Geleverd op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('receiver_name')
                            ->label('Ontvanger')
                            ->placeholder('-'),

                        TextEntry::make('driver_name')
                            ->label('Bezorger')
                            ->placeholder('-'),

                        ImageEntry::make('signature_path')
                            ->label('Handtekening ontvanger')
                            ->disk(UploadStorage::diskName())
                            ->height(120)
                            ->placeholder('Geen handtekening'),

                        ImageEntry::make('driver_signature_path')
                            ->label('Handtekening bezorger')
                            ->disk(UploadStorage::diskName())
                            ->height(120)
                            ->placeholder('Geen handtekening'),
                    ]),

                Section::make('Kratten')
                    ->icon('heroicon-o-archive-box')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('crates_given')
                            ->label('Meegegeven kratten')
                            ->state(fn (Delivery $record): int => app(DeliveryNoteService::class)->crateTotals($record->order)['given']),

                        TextEntry::make('crates_returned')
                            ->label('Retour ontvangen kratten')
                            ->state(fn (Delivery $record): int => app(DeliveryNoteService::class)->crateTotals($record->order)['returned']),
                    ]),

                Section::make('Leveringsregels')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        TextEntry::make('deviation_summary')
                            ->label('Afwijkingen & retour-notities')
                            ->html()
                            ->state(fn ($record): ?string => DeliveryDeviationSummary::html($record))
                            ->placeholder('Geen afwijkingen')
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
