<?php

namespace App\Filament\Resources\QueueFailedJobs;

use App\Filament\Resources\QueueFailedJobs\Pages\ListQueueFailedJobs;
use App\Filament\Resources\QueueFailedJobs\Pages\ViewQueueFailedJob;
use App\Filament\Resources\QueueFailedJobs\Schemas\QueueFailedJobInfolist;
use App\Filament\Resources\QueueFailedJobs\Tables\QueueFailedJobsTable;
use App\Models\QueueFailedJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class QueueFailedJobResource extends Resource
{
    protected static ?string $model = QueueFailedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ExclamationTriangle;

    protected static ?string $navigationLabel = 'Mislukte jobs';

    protected static ?string $modelLabel = 'Mislukte job';

    protected static ?string $pluralModelLabel = 'Mislukte jobs';

    protected static string|null|\UnitEnum $navigationGroup = 'Systeem / Beheer';

    protected static ?int $navigationSort = 92;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return QueueFailedJobInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QueueFailedJobsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQueueFailedJobs::route('/'),
            'view' => ViewQueueFailedJob::route('/{record}'),
        ];
    }
}
