<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\UploadStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factuur '.$this->invoice->displayInvoiceNumber().' — contant voldaan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.paid',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! UploadStorage::exists($this->invoice->pdf_path)) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => (string) UploadStorage::read($this->invoice->pdf_path),
                'factuur-'.$this->invoice->displayInvoiceNumber().'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
