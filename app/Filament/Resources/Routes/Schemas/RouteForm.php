<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('driver_id')
                    ->searchable()
                    ->label('Chauffeur')
                    ->preload()
                    ->relationship('driver', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('role', UserRole::DRIVER))
                    ->required(),
                Select::make('vehicle_id')
                    ->searchable(['brand', 'model', 'license_plate'])
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->brand} {$record->model} | {$record->license_plate}")
                    ->relationship('vehicle', 'license_plate')
                    ->label('Voertuig')
                    ->required(),
                DatePicker::make('route_date')
                    ->label('Bezorg datum')
                    ->required(),
            ]);
    }
}
