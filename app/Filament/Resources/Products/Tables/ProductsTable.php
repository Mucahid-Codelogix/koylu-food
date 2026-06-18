<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\RecordDeletionActions;
use App\Jobs\SyncProductToExact;
use App\Models\Product;
use App\Support\UploadStorage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(),
                TextColumn::make('product_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('vat_category')
                    ->label('BTW')
                    ->badge(),
                TextColumn::make('exact_article_code')
                    ->label('Exact code')
                    ->toggleable(),
                TextColumn::make('exact_sync_status')
                    ->label('Exact')
                    ->badge()
                    ->state(function (Product $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'Fout';
                        }

                        if (filled($record->exact_synced_at)) {
                            return 'Gesynced';
                        }

                        return 'Niet gesynced';
                    })
                    ->color(function (Product $record): string {
                        if (filled($record->exact_sync_error)) {
                            return 'danger';
                        }

                        if (filled($record->exact_synced_at)) {
                            return 'success';
                        }

                        return 'gray';
                    })
                    ->tooltip(fn (Product $record): ?string => $record->exact_sync_error),
                TextColumn::make('packagings_count')
                    ->label('Verpakkingen')
                    ->counts('packagings'),
                TextColumn::make('product_suppliers_count')
                    ->label('Leveranciers')
                    ->counts('productSuppliers'),
                TextColumn::make('default_price_per_kg')
                    ->label('Prijs/kg')
                    ->state(function ($record): ?string {
                        $price = $record->defaultProductSupplier()?->price_per_kg;

                        return $price !== null
                            ? '€ '.number_format((float) $price, 2, ',', '.')
                            : null;
                    }),
                TextColumn::make('min_order_quantity')
                    ->label('Min. afname')
                    ->numeric(decimalPlaces: 2),
                ImageColumn::make('image_path')
                    ->disk(UploadStorage::diskName())
                    ->checkFileExistence(false),
                IconColumn::make('is_active')
                    ->label('Actief')
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
                    ->action(function (Product $record): void {
                        SyncProductToExact::dispatch($record);

                        Notification::make()
                            ->title('Sync gestart')
                            ->body('Het product wordt naar Exact gesynchroniseerd.')
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
