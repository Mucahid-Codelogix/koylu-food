<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class RecordDeletionActions
{
    public static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Deactiveren')
            ->icon('heroicon-o-no-symbol')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Record deactiveren')
            ->modalDescription('Het record blijft bewaard maar is niet meer actief in de shop of voor nieuwe bestellingen.')
            ->visible(fn (Model $record): bool => (bool) ($record->is_active ?? false))
            ->action(function (Model $record): void {
                $record->update(['is_active' => false]);

                Notification::make()
                    ->title('Gedeactiveerd')
                    ->body('Het record is gedeactiveerd.')
                    ->success()
                    ->send();
            });
    }

    public static function safeDeleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Model $record): bool => method_exists($record, 'canBeDeleted') && $record->canBeDeleted())
            ->before(function (DeleteAction $action, Model $record): void {
                if (! method_exists($record, 'canBeDeleted') || $record->canBeDeleted()) {
                    return;
                }

                Notification::make()
                    ->title('Verwijderen niet mogelijk')
                    ->body($record->deletionBlockReason())
                    ->danger()
                    ->send();

                $action->halt();
            });
    }

    public static function safeDeleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->before(function (DeleteBulkAction $action, Collection $records): void {
                $blocked = $records->filter(
                    fn (Model $record): bool => method_exists($record, 'canBeDeleted') && ! $record->canBeDeleted(),
                );

                if ($blocked->isEmpty()) {
                    return;
                }

                $reasons = $blocked
                    ->map(fn (Model $record): string => $record->deletionBlockReason() ?? 'Onbekende reden')
                    ->unique()
                    ->implode(' ');

                Notification::make()
                    ->title('Verwijderen niet mogelijk')
                    ->body($blocked->count().' record(s) kunnen niet worden verwijderd. '.$reasons)
                    ->danger()
                    ->send();

                $action->halt();
            });
    }
}
