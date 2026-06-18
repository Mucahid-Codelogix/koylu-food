<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Support\RecordDeletionActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RecordDeletionActions::safeDeleteBulkAction(),
                ]),
            ]);
    }
}
