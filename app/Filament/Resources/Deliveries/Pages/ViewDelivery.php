<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use App\Models\Delivery;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliveryNote')
                ->label('Leverbon bekijken')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (Delivery $record): string => route('leverbon.download', ['delivery' => $record, 'bekijken' => 1]))
                ->openUrlInNewTab(),

            EditAction::make(),
        ];
    }
}
