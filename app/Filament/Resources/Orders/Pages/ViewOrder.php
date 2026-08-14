<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('workDeliveryNote')
                ->label('Werkbon printen')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (Order $record): string => route('leverbon.concept', $record))
                ->openUrlInNewTab()
                ->visible(fn (Order $record): bool => filled($record->delivery_note_number)),

            Action::make('finalDeliveryNote')
                ->label('Definitieve leverbon')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (Order $record): ?string => $record->delivery
                    ? route('leverbon.download', $record->delivery)
                    : null)
                ->openUrlInNewTab()
                ->visible(fn (Order $record): bool => $record->delivery !== null),

            EditAction::make(),
        ];
    }
}
