<?php

namespace App\Filament\Resources\ExactSyncLogs\Tables;

use App\Models\ExactSyncLog;
use App\Services\Exact\ExactSyncLogger;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExactSyncLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tijdstip')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('Actie')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'push_customer' => 'Klant push',
                        'push_product' => 'Product push',
                        'push_supplier' => 'Leverancier push',
                        'push_invoice' => 'Factuur push',
                        default => $state,
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ExactSyncLogger::STATUS_SUCCESS ? 'Gelukt' : 'Mislukt')
                    ->color(fn (string $state): string => $state === ExactSyncLogger::STATUS_SUCCESS ? 'success' : 'danger'),

                TextColumn::make('syncable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => match (class_basename((string) $state)) {
                        'Customer' => 'Klant',
                        'Product' => 'Product',
                        'Supplier' => 'Leverancier',
                        'Invoice' => 'Factuur',
                        default => class_basename((string) $state) ?: '-',
                    }),

                TextColumn::make('syncable_id')
                    ->label('Record ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('message')
                    ->label('Bericht')
                    ->limit(40)
                    ->placeholder('-'),

                TextColumn::make('error')
                    ->label('Fout')
                    ->limit(50)
                    ->placeholder('-')
                    ->color('danger')
                    ->tooltip(fn (ExactSyncLog $record): ?string => $record->error),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        ExactSyncLogger::STATUS_SUCCESS => 'Gelukt',
                        ExactSyncLogger::STATUS_FAILED => 'Mislukt',
                    ]),

                SelectFilter::make('action')
                    ->label('Actie')
                    ->options([
                        'push_customer' => 'Klant push',
                        'push_product' => 'Product push',
                        'push_supplier' => 'Leverancier push',
                        'push_invoice' => 'Factuur push',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
