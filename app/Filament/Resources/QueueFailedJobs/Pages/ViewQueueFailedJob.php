<?php

namespace App\Filament\Resources\QueueFailedJobs\Pages;

use App\Filament\Resources\QueueFailedJobs\Actions\RetryQueueFailedJobAction;
use App\Filament\Resources\QueueFailedJobs\QueueFailedJobResource;
use Filament\Resources\Pages\ViewRecord;

class ViewQueueFailedJob extends ViewRecord
{
    protected static string $resource = QueueFailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RetryQueueFailedJobAction::make(),
        ];
    }
}
