<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Voertuiginformatie')
                    ->columns(2)
                    ->schema([

                        TextInput::make('brand')
                            ->label('Merk')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('model')
                            ->label('Model')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('license_plate')
                            ->label('Kenteken')
                            ->required()
                            ->maxLength(50),

                    ]),
            ]);
    }
}
