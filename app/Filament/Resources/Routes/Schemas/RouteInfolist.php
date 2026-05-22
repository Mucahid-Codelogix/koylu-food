<?php

namespace App\Filament\Resources\Routes\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RouteInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Routegegevens')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('driver.name')
                            ->label('Chauffeur')
                            ->placeholder('-'),

                        TextEntry::make('vehicle.license_plate')
                            ->label('Voertuig')
                            ->formatStateUsing(fn ($record): string => $record->vehicle
                                ? "{$record->vehicle->brand} {$record->vehicle->model} ({$record->vehicle->license_plate})"
                                : '—')
                            ->placeholder('-'),

                        TextEntry::make('route_date')
                            ->label('Bezorgdatum')
                            ->date('d-m-Y'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ]),

                Section::make('Voortgang')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('started_at')
                            ->label('Gestart op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Nog niet gestart'),

                        TextEntry::make('loading_completed_at')
                            ->label('Laden afgerond')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Nog niet geladen'),

                        TextEntry::make('completed_at')
                            ->label('Afgerond op')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Nog niet afgerond'),
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
