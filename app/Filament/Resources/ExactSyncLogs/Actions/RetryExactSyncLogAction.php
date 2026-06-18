<?php

namespace App\Filament\Resources\ExactSyncLogs\Actions;

use App\Models\ExactSyncLog;
use App\Services\Exact\ExactSyncRetryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RetryExactSyncLogAction
{
    public static function make(): Action
    {
        return Action::make('retryExactSync')
            ->label('Opnieuw proberen')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (ExactSyncLog $record): bool => app(ExactSyncRetryService::class)->canRetry($record))
            ->requiresConfirmation()
            ->modalHeading('Sync opnieuw proberen')
            ->modalDescription('De mislukte sync wordt opnieuw in de wachtrij gezet.')
            ->action(function (ExactSyncLog $record): void {
                app(ExactSyncRetryService::class)->retry($record);

                Notification::make()
                    ->title('Sync opnieuw gestart')
                    ->success()
                    ->send();
            });
    }
}
