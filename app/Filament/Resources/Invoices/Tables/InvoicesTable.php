<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Factuurnummer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('order.customer.company_name')
                    ->label('Klant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('invoice_date')
                    ->label('Datum')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vervaldatum')
                    ->date('d-m-Y')
                    ->sortable()
                    ->color(fn (Invoice $record) => $record->status !== 'paid' && $record->due_date?->isPast()
                        ? 'danger' : null
                    ),

                TextColumn::make('total_amount')
                    ->label('Totaal')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Bekijken'),

                Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->status === 'concept')
                    ->requiresConfirmation()
                    ->modalHeading('Factuur goedkeuren')
                    ->modalDescription('PDF en UBL worden aangemaakt. Weet je zeker dat je wilt doorgaan?')
                    ->action(function (Invoice $record) {
                        $service = app(InvoiceService::class);
                        $service->generatePdf($record);
                        $service->generateUbl($record);

                        $record->update([
                            'status' => 'sent',
                            'sent_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Factuur goedgekeurd!')
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    Action::make('download_pdf')
                        ->label('PDF downloaden')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('gray')
                        ->url(fn (Invoice $record) => route('invoice.pdf', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Invoice $record) => $record->pdf_path !== null),

                    Action::make('download_ubl')
                        ->label('UBL downloaden')
                        ->icon('heroicon-o-code-bracket')
                        ->color('gray')
                        ->url(fn (Invoice $record) => route('invoice.ubl', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (Invoice $record) => $record->ubl_path !== null),

                    Action::make('mark_paid')
                        ->label('Markeer als betaald')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn (Invoice $record) => $record->status === InvoiceStatus::SENT)
                        ->requiresConfirmation()
                        ->action(fn (Invoice $record) => $record->update(['status' => InvoiceStatus::PAID])),

                    Action::make('edit')
                        ->label('Bewerken')
                        ->icon('heroicon-o-pencil')
                        ->url(fn (Invoice $record) => InvoiceResource::getUrl('edit', ['record' => $record])),
                ])->tooltip('Meer acties'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
