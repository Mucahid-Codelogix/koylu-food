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
                Section::make()
                    ->columns()
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('status'),
                        TextEntry::make('order_date')
                            ->date(),
                        TextEntry::make('delivery_date')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('total_price')
                            ->money('Eur'),
                        TextEntry::make('notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
