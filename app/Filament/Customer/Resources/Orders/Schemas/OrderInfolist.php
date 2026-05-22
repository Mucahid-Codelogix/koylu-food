<?php

namespace App\Filament\Customer\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bestelling')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Bestelnummer')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        TextEntry::make('order_date')
                            ->label('Besteldatum')
                            ->date('d-m-Y'),

                        TextEntry::make('delivery_date')
                            ->label('Leverdatum')
                            ->date('d-m-Y')
                            ->placeholder('-'),

                        TextEntry::make('total_price')
                            ->label('Totaalbedrag')
                            ->money('EUR'),
                    ]),

                Section::make('Opmerkingen')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notities')
                            ->placeholder('Geen opmerkingen')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
