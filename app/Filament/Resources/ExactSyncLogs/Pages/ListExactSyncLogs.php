<?php

namespace App\Filament\Resources\ExactSyncLogs\Pages;

use App\Filament\Resources\ExactSyncLogs\ExactSyncLogResource;
use Filament\Resources\Pages\ListRecords;

class ListExactSyncLogs extends ListRecords
{
    protected static string $resource = ExactSyncLogResource::class;
}
