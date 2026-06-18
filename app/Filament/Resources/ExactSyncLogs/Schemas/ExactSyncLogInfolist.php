<?php

namespace App\Filament\Resources\ExactSyncLogs\Schemas;

use App\Services\Exact\ExactSyncLogger;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExactSyncLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sync-gebeurtenis')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Tijdstip')
                            ->dateTime('d-m-Y H:i:s'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === ExactSyncLogger::STATUS_SUCCESS ? 'Gelukt' : 'Mislukt')
                            ->color(fn (string $state): string => $state === ExactSyncLogger::STATUS_SUCCESS ? 'success' : 'danger'),

                        TextEntry::make('action')
                            ->label('Actie')
                            ->badge(),

                        TextEntry::make('syncable_type')
                            ->label('Recordtype')
                            ->formatStateUsing(fn (?string $state): string => match (class_basename((string) $state)) {
                                'Customer' => 'Klant',
                                'Product' => 'Product',
                                'Supplier' => 'Leverancier',
                                'Invoice' => 'Factuur',
                                default => (string) $state,
                            }),

                        TextEntry::make('syncable_id')
                            ->label('Record ID'),

                        TextEntry::make('message')
                            ->label('Bericht')
                            ->placeholder('-')
                            ->columnSpanFull(),

                        TextEntry::make('error')
                            ->label('Foutmelding')
                            ->placeholder('-')
                            ->color('danger')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
