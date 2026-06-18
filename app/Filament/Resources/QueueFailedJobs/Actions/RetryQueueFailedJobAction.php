<?php

namespace App\Filament\Resources\QueueFailedJobs\Actions;

use App\Models\QueueFailedJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;

class RetryQueueFailedJobAction
{
    public static function make(): Action
    {
        return Action::make('retryFailedJob')
            ->label('Opnieuw in wachtrij')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Job opnieuw proberen')
            ->modalDescription('De mislukte job wordt opnieuw in de wachtrij geplaatst.')
            ->action(function (QueueFailedJob $record): void {
                Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                Notification::make()
                    ->title('Job opnieuw in wachtrij geplaatst')
                    ->success()
                    ->send();
            });
    }
}
