<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function downloadPdf(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        abort_unless($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path), 404);

        return Storage::disk('public')->download(
            $invoice->pdf_path,
            $invoice->invoice_number.'.pdf'
        );
    }

    public function downloadUbl(Invoice $invoice)
    {
        $this->authorizeAccess($invoice);

        abort_unless($invoice->ubl_path && Storage::disk('public')->exists($invoice->ubl_path), 404);

        return Storage::disk('public')->download(
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
