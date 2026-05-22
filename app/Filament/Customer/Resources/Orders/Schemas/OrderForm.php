<?php

namespace App\Filament\Customer\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bestelling')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Bestelnummer')
                            ->disabled()
                            ->dehydrated(),

                        Select::make('status')
                            ->label('Status')
                            ->options(OrderStatus::class)
                            ->disabled()
                            ->dehydrated()
                            ->native(false),

                        DatePicker::make('order_date')
                            ->label('Besteldatum')
                            ->disabled()
                            ->dehydrated()
                            ->displayFormat('d-m-Y'),

                        DatePicker::make('delivery_date')
                            ->label('Leverdatum')
                            ->disabled()
                            ->dehydrated()
                            ->displayFormat('d-m-Y'),

                        TextInput::make('total_price')
                            ->label('Totaalbedrag')
                            ->disabled()
                            ->dehydrated()
                            ->prefix('€')
                            ->numeric(),
                    ]),

                Section::make('Opmerkingen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notities')
                            ->disabled()
                            ->dehydrated()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
