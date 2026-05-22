<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Routeplanning')
                    ->description('Koppel chauffeur, voertuig en bezorgdatum. Stops voeg je toe via het tabblad Route stops.')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->schema([
                        Select::make('driver_id')
                            ->label('Chauffeur')
                            ->searchable()
                            ->preload()
                            ->relationship(
                                'driver',
                                'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('role', UserRole::DRIVER),
                            )
                            ->required(),

                        Select::make('vehicle_id')
                            ->label('Voertuig')
                            ->searchable(['brand', 'model', 'license_plate'])
                            ->preload()
                            ->getOptionLabelFromRecordUsing(
                                fn (Model $record): string => "{$record->brand} {$record->model} | {$record->license_plate}",
                            )
                            ->relationship('vehicle', 'license_plate')
                            ->required(),

                        DatePicker::make('route_date')
                            ->label('Bezorgdatum')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
