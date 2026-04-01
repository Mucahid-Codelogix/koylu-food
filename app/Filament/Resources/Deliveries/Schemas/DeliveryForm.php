<?php

namespace App\Filament\Resources\Deliveries\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DeliveryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                DateTimePicker::make('delivered_at'),
                TextInput::make('receiver_name'),
                TextInput::make('signature_path'),
                TextInput::make('status')
                    ->required(),
            ]);
    }
}
