<?php

namespace App\Filament\Resources\QueueFailedJobs\Tables;

use App\Filament\Resources\QueueFailedJobs\Actions\RetryQueueFailedJobAction;
use App\Models\QueueFailedJob;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QueueFailedJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Mislukt op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                TextColumn::make('job_name')
                    ->label('Job')
                    ->state(fn (QueueFailedJob $record): string => $record->jobName())
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where('payload', 'like', '%'.$search.'%');
                    })
                    ->wrap(),

                TextColumn::make('queue')
                    ->label('Queue')
                    ->badge(),

                TextColumn::make('connection')
                    ->label('Connectie')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('exception_summary')
                    ->label('Fout')
                    ->state(fn (QueueFailedJob $record): string => $record->exceptionSummary())
                    ->limit(60)
                    ->color('danger')
                    ->tooltip(fn (QueueFailedJob $record): string => $record->exceptionSummary()),
            ])
            ->filters([
                Filter::make('exact_only')
                    ->label('Alleen Exact-jobs')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        $query->where('payload', 'like', '%Exact%')
                            ->orWhere('payload', 'like', '%ImportCustomersFromExact%')
                            ->orWhere('payload', 'like', '%ImportProductsFromExact%')
                            ->orWhere('payload', 'like', '%ImportSuppliersFromExact%');
                    }))
                    ->default(),
            ])
            ->recordActions([
                RetryQueueFailedJobAction::make(),
                ViewAction::make(),
                DeleteAction::make()
                    ->label('Verwijderen')
                    ->modalHeading('Mislukte job verwijderen'),
            ]);
    }
}
