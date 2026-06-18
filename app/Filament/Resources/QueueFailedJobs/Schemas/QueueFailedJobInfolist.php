<?php

namespace App\Filament\Resources\QueueFailedJobs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QueueFailedJobInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Job')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('failed_at')
                            ->label('Mislukt op')
                            ->dateTime('d-m-Y H:i:s'),

                        TextEntry::make('uuid')
                            ->label('UUID')
                            ->copyable(),

                        TextEntry::make('job_name')
                            ->label('Job')
                            ->state(fn ($record): string => $record->jobName())
                            ->columnSpanFull(),

                        TextEntry::make('queue')
                            ->label('Queue')
                            ->badge(),

                        TextEntry::make('connection')
                            ->label('Connectie'),

                        TextEntry::make('exception')
                            ->label('Exception')
                            ->color('danger')
                            ->columnSpanFull()
                            ->prose(),
                    ]),
            ]);
    }
}
