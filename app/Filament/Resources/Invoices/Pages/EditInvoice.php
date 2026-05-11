<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('download_pdf')
                ->label('PDF downloaden')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('invoice.pdf', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->pdf_path !== null),

            Action::make('download_ubl')
                ->label('UBL downloaden')
                ->icon('heroicon-o-code-bracket')
                ->color('gray')
                ->url(fn () => route('invoice.ubl', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->ubl_path !== null),

            Action::make('approve')
                ->label('Goedkeuren & versturen')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $this->record->status === 'concept')
                ->requiresConfirmation()
                ->modalHeading('Factuur goedkeuren')
                ->modalDescription('PDF en UBL worden aangemaakt. Weet je zeker dat je wilt doorgaan?')
                ->action(function () {
                    $service = app(InvoiceService::class);
                    $service->generatePdf($this->record);
                    $service->generateUbl($this->record);

                    $this->record->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Factuur goedgekeurd!')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'sent_at']);
                }),
        ];
    }
}
