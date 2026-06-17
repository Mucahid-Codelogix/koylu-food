<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Filament\Resources\Invoices\Actions\InvoiceActionGroup;
use App\Filament\Resources\Invoices\InvoiceResource;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            InvoiceActionGroup::make(
                includeEdit: false,
                afterApprove: fn () => $this->refreshFormData(['status', 'sent_at', 'pdf_path', 'ubl_path']),
            ),
        ];
    }
}
