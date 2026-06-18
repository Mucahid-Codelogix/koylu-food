<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Support\RecordDeletionActions;
use App\Jobs\SyncSupplierToExact;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncToExact')
                ->label('Sync naar Exact')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    SyncSupplierToExact::dispatch($this->getRecord());

                    Notification::make()
                        ->title('Sync gestart')
                        ->body('De leverancier wordt naar Exact gesynchroniseerd.')
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            RecordDeletionActions::deactivateAction(),
            RecordDeletionActions::safeDeleteAction(),
        ];
    }
}
