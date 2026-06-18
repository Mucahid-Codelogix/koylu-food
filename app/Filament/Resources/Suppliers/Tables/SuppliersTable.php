<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Filament\Support\RecordDeletionActions;
use App\Jobs\SyncSupplierToExact;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->searchable(),
                TextColumn::make('kvk_number')
                    ->searchable(),
                TextColumn::make('exact_sync_status')
                    ->label('Exact')
                    ->badge()
                    ->state(function (Supplier $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'Fout';
                        }

                        if (filled($record->exact_synced_at)) {
                            return 'Gesynced';
                        }

                        return 'Niet gesynced';
                    })
                    ->color(function (Supplier $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'danger';
                        }

                        if (filled($record->exact_synced_at)) {
                            return 'success';
                        }

                        return 'gray';
                    })
                    ->tooltip(fn (Supplier $record): ?string => $record->exact_sync_error),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('syncToExact')
                    ->label('Sync naar Exact')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Supplier $record): void {
                        SyncSupplierToExact::dispatch($record);

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('De leverancier wordt naar Exact gesynchroniseerd.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RecordDeletionActions::safeDeleteBulkAction(),
                ]),
            ]);
    }
}
