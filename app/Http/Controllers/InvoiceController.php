<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\UploadStorage;

class InvoiceController extends Controller
{
    public function downloadPdf(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        abort_unless($invoice->pdf_path && UploadStorage::disk()->exists($invoice->pdf_path), 404);

        return UploadStorage::disk()->download(
            $invoice->pdf_path,
            $invoice->invoice_number.'.pdf'
        );
    }

    public function downloadUbl(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        abort_unless($invoice->ubl_path && UploadStorage::disk()->exists($invoice->ubl_path), 404);

        return UploadStorage::disk()->download(
            $invoice->ubl_path,
            $invoice->invoice_number.'.xml'
        );
    }

    private function authorizeAccess(Invoice $invoice): void
    {
        $user = auth()->user();

        if ($user->role === 'customer') {
            abort_unless(
                $invoice->order->customer_id === $user->customer_id,
                403
            );
        }
    }
}
