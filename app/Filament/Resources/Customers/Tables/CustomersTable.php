<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Support\RecordDeletionActions;
use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;
use App\Support\ExactSyncBadge;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Bedrijf')
                    ->searchable(),
                TextColumn::make('contact_name')
                    ->label('Contact persoon')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefoonnummer')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Adres')
                    ->searchable(),
                TextColumn::make('postal_code')
                    ->label('Post code')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('Stad')
                    ->searchable(),
                TextColumn::make('country')
                    ->label('Land')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->label('BTW-nummer')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exact_sync_status')
                    ->label('Exact')
                    ->badge()
                    ->state(fn (Customer $record): string => ExactSyncBadge::label(
                        $record->exact_sync_error,
                        $record->exact_synced_at,
                        filled($record->exact_account_id),
                    ))
                    ->color(fn (Customer $record): string => ExactSyncBadge::color(
                        $record->exact_sync_error,
                        $record->exact_synced_at,
                        filled($record->exact_account_id),
                    ))
                    ->tooltip(fn (Customer $record): ?string => $record->exact_sync_error),
                TextColumn::make('min_order_amount')
                    ->label('Minimale order afnamen')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('is Actief')
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
                    ->action(function (Customer $record): void {
                        $record->updateQuietly(['exact_sync_error' => null]);
                        SyncCustomerToExact::dispatch($record);

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('De klant wordt naar Exact gesynchroniseerd.')
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
