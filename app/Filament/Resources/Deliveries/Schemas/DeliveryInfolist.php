<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Enums\PaymentMethod;
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

                        self::signatureEntry('signature_path', 'Handtekening ontvanger'),

                        self::signatureEntry('driver_signature_path', 'Handtekening bezorger'),
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

                Section::make('Contante betaling')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->visible(fn (Delivery $record): bool => $record->order?->invoice?->payment_method === PaymentMethod::CASH)
                    ->schema([
                        TextEntry::make('cash_amount')
                            ->label('Contant ontvangen bedrag')
                            ->state(fn (Delivery $record): ?float => $record->order?->invoice?->paid_amount ?? $record->order?->invoice?->total_amount)
                            ->money('EUR', locale: 'nl'),

                        self::signatureEntry('cash_signature_path', 'Handtekening bezorger — geld ontvangen'),
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

    /**
     * Handtekening binnen de breedte van de sectie: vaste hoogte, nooit breder dan
     * het blok, verhouding behouden.
     */
    private static function signatureEntry(string $name, string $label): ImageEntry
    {
        return ImageEntry::make($name)
            ->label($label)
            ->disk(UploadStorage::diskName())
            ->imageHeight('8rem')
            ->extraImgAttributes([
                'style' => 'max-width: 100%; object-fit: contain; object-position: left center;',
            ])
            ->placeholder('Geen handtekening');
    }
}
