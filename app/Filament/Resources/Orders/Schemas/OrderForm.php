<?php

namespace App\Filament\Resources\Orders\Schemas;

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
                            ->required()
                            ->maxLength(255),

                        Select::make('customer_id')
                            ->label('Klant')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->label('Status')
                            ->options(OrderStatus::class)
                            ->required()
                            ->default(OrderStatus::PLACED)
                            ->native(false),

                        DatePicker::make('order_date')
                            ->label('Besteldatum')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y'),

                        DatePicker::make('delivery_date')
                            ->label('Leverdatum')
                            ->native(false)
                            ->displayFormat('d-m-Y'),
                    ]),

                Section::make('Bedrag')
                    ->columns(2)
                    ->schema([
                        TextInput::make('total_price')
                            ->label('Totaalbedrag')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('€')
                            ->minValue(0)
                            ->step(0.01),
                    ]),

                Section::make('Opmerkingen')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notities')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
