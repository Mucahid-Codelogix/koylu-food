<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use App\Enums\DeliveryStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Levering')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
                        Select::make('order_id')
                            ->label('Bestelling')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->label('Status')
                            ->options(DeliveryStatus::class)
                            ->required()
                            ->native(false),

                        DateTimePicker::make('delivered_at')
                            ->label('Geleverd op')
                            ->native(false)
                            ->displayFormat('d-m-Y H:i'),

                        TextInput::make('receiver_name')
                            ->label('Ontvanger')
                            ->maxLength(255),

                        TextInput::make('signature_path')
                            ->label('Handtekening (pad)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
