<?php

namespace App\Filament\Resources\QueueFailedJobs\Pages;

use App\Filament\Resources\QueueFailedJobs\QueueFailedJobResource;
use Filament\Resources\Pages\ListRecords;

class ListQueueFailedJobs extends ListRecords
{
    protected static string $resource = QueueFailedJobResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
