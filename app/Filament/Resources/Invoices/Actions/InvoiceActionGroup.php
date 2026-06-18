<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;

class InvoiceActionGroup
{
    public static function make(
        bool $includeView = true,
        bool $includeEdit = true,
        bool $includeDelete = false,
        ?\Closure $afterApprove = null,
    ): ActionGroup {
        $actions = [];

        if ($includeView) {
            $actions[] = ViewAction::make()
                ->label('Bekijken');
        }

        if ($includeEdit) {
            $actions[] = Action::make('edit')
                ->label('Bewerken')
                ->icon('heroicon-o-pencil')
                ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record]));
        }

        $actions[] = InvoicePdfAction::makeOpenAction();
        $actions[] = ApproveInvoiceAction::make($afterApprove);
        $actions[] = Action::make('download_ubl')
            ->label('UBL downloaden')
            ->icon('heroicon-o-code-bracket')
            ->visible(fn (Invoice $record): bool => $record->ubl_path !== null)
            ->url(fn (Invoice $record): string => route('invoice.ubl', $record))
            ->openUrlInNewTab();
        $actions[] = Action::make('mark_paid')
            ->label('Markeer als betaald')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->status === InvoiceStatus::SENT)
            ->requiresConfirmation()
            ->action(fn (Invoice $record) => $record->update(['status' => InvoiceStatus::PAID]));

        if ($includeDelete) {
            $actions[] = DeleteAction::make()
                ->label('Verwijderen');
        }

        return ActionGroup::make($actions)
            ->label('Acties')
            ->icon('heroicon-o-ellipsis-vertical')
            ->color('gray')
            ->button();
    }
}
