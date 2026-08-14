<?php

namespace App\Jobs;

use App\Mail\InvoicePaidMail;
use App\Models\Invoice;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCashEntryService;
use App\Services\Exact\ExactInvoiceSyncService;
use App\Services\Exact\ExactMatchService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Handelt een contant betaalde factuur in één keer af: boeken in Exact (zonder
 * admin-goedkeuring), kasboeking wegschrijven, PDF/UBL maken en de factuur naar
 * de klant mailen.
 */
class ProcessCashPaidInvoice implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(public Invoice $invoice) {}

    public function handle(
        ExactInvoiceSyncService $syncService,
        ExactCashEntryService $cashEntryService,
        ExactOnlineClient $client,
        InvoiceService $invoiceService,
    ): void {
        $lock = Cache::lock("cash-invoice-{$this->invoice->id}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->invoice->refresh();

            if ($client->isConnected()) {
                $this->bookInExact($syncService, $cashEntryService, $invoiceService);
            } else {
                ExactSyncLogger::failed(
                    $this->invoice,
                    'push_cash_invoice',
                    'Exact Online is niet gekoppeld; factuur is wel aangemaakt en verstuurd.',
                );
            }

            $this->ensurePdfExists($invoiceService);
            $this->sendToCustomer();
        } finally {
            $lock->release();
        }
    }

    private function bookInExact(
        ExactInvoiceSyncService $syncService,
        ExactCashEntryService $cashEntryService,
        InvoiceService $invoiceService,
    ): void {
        try {
            if (blank($this->invoice->exact_invoice_id)) {
                $result = $syncService->push($this->invoice->fresh([
                    'order.customer',
                    'order.items.product',
                    'order.delivery.items',
                ]));

                $this->invoice->updateQuietly([
                    'exact_invoice_id' => $result->invoiceId,
                    'exact_document_number' => $result->documentNumber,
                    'exact_synced_at' => now(),
                    'exact_sync_error' => null,
                ]);

                $invoice = $this->invoice->fresh([
                    'order.customer',
                    'order.items.product',
                    'order.delivery.items',
                ]);

                $invoiceService->generatePdf($invoice);
                $invoiceService->generateUbl($invoice);

                ExactSyncLogger::success(
                    $this->invoice,
                    'push_cash_invoice',
                    sprintf('Contante factuur geboekt als %s.', $result->documentNumber ?? $result->invoiceId),
                );
            }

            $cashEntryService->registerPayment($this->invoice->fresh());
        } catch (ExactApiException $exception) {
            $this->invoice->updateQuietly([
                'exact_sync_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->invoice, 'push_cash_invoice', $exception->getMessage());

            return;
        }

        $this->matchInExact();
    }

    /**
     * Lettert de kasboeking af tegen de factuur. Dit loopt via de legacy XML API
     * en is daarmee het kwetsbaarste onderdeel van de keten; een mislukking mag
     * de al geboekte factuur nooit raken. De admin kan het altijd overdoen.
     */
    private function matchInExact(): void
    {
        $matchService = app(ExactMatchService::class);

        if (! $matchService->isEnabled()) {
            return;
        }

        try {
            $matchService->matchCashPayment($this->invoice->fresh(['order.customer']));
        } catch (Throwable $exception) {
            $this->invoice->updateQuietly([
                'exact_match_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->invoice, 'match_cash_payment', $exception->getMessage());
        }
    }

    /**
     * Zonder Exact-koppeling heeft de factuur nog geen PDF, maar de klant moet
     * hem wel kunnen ontvangen en inzien.
     */
    private function ensurePdfExists(InvoiceService $invoiceService): void
    {
        $invoice = $this->invoice->fresh();

        if (blank($invoice->pdf_path)) {
            $invoiceService->generatePdf($invoice);
        }
    }

    private function sendToCustomer(): void
    {
        $invoice = $this->invoice->fresh(['order.customer']);
        $recipient = $invoice->order->customer->email;

        if (blank($recipient)) {
            return;
        }

        Mail::to($recipient)->send(new InvoicePaidMail($invoice));

        $invoice->updateQuietly(['sent_at' => now()]);
    }
}
