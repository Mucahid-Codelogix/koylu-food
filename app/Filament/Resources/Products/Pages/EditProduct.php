<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\RecordDeletionActions;
use App\Jobs\SyncProductToExact;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): ?string
    {
        $packagings = $this->getRecord()->packagings()->where('is_active', true)->count();
        $suppliers = $this->getRecord()->productSuppliers()->where('is_active', true)->count();

        if ($packagings === 0 || $suppliers === 0) {
            return 'Let op: dit product is pas zichtbaar in de shop met minstens één actieve verpakking én leverancier.';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncToExact')
                ->label('Sync naar Exact')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    SyncProductToExact::dispatch($this->getRecord());

                    Notification::make()
                        ->title('Sync gestart')
                        ->body('Het product wordt naar Exact gesynchroniseerd.')
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            RecordDeletionActions::deactivateAction(),
            RecordDeletionActions::safeDeleteAction(),
        ];
    }
}
