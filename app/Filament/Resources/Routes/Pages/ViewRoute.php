<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Filament\Resources\Routes\RouteResource;
use App\Models\Route;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoute extends ViewRecord
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliveryNotes')
                ->label('Leverbonnen printen')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (Route $record): string => route('leverbon.route', $record))
                ->openUrlInNewTab()
                ->visible(fn (Route $record): bool => $record->routeStops()->exists()),

            EditAction::make(),
        ];
    }
}
