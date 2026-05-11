<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Voertuiginformatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('brand')
                            ->label('Merk')
                            ->placeholder('-'),

                        TextEntry::make('model')
                            ->label('Model')
                            ->placeholder('-'),

                        TextEntry::make('license_plate')
                            ->label('Kenteken')
                            ->placeholder('-'),

                    ]),

                Section::make('Systeeminformatie')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('created_at')
                            ->label('Aangemaakt op')
                            ->dateTime()
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Laatst bijgewerkt')
                            ->dateTime()
                            ->placeholder('-'),

                    ]),
            ]);
    }
}
