<?php

namespace App\Filament\Resources\Deliveries\Tables;

use App\Models\Delivery;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.id')
                    ->searchable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('receiver_name')
                    ->searchable(),
                TextColumn::make('signature_path')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
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
                Action::make('deliveryNote')
                    ->label('Leverbon')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Delivery $record): string => route('leverbon.download', ['delivery' => $record, 'bekijken' => 1]))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
