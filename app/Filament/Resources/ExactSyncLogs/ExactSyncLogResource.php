<?php

namespace App\Filament\Resources\ExactSyncLogs;

use App\Filament\Resources\ExactSyncLogs\Pages\ListExactSyncLogs;
use App\Filament\Resources\ExactSyncLogs\Pages\ViewExactSyncLog;
use App\Filament\Resources\ExactSyncLogs\Schemas\ExactSyncLogInfolist;
use App\Filament\Resources\ExactSyncLogs\Tables\ExactSyncLogsTable;
use App\Models\ExactSyncLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExactSyncLogResource extends Resource
{
    protected static ?string $model = ExactSyncLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $navigationLabel = 'Exact sync-log';

    protected static ?string $modelLabel = 'Sync-log';

    protected static ?string $pluralModelLabel = 'Exact sync-logs';

    protected static string|null|\UnitEnum $navigationGroup = 'Systeem / Beheer';

    protected static ?int $navigationSort = 91;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExactSyncLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExactSyncLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExactSyncLogs::route('/'),
            'view' => ViewExactSyncLog::route('/{record}'),
        ];
    }
}
