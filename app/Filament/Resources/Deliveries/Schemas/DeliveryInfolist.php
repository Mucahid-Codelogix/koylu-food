<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Support\DeliveryDeviationSummary;
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

                        TextEntry::make('signature_path')
                            ->label('Handtekening')
                            ->placeholder('Geen handtekening')
                            ->columnSpanFull(),
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
