<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactInvoiceSyncService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class PushInvoiceToExact implements ShouldQueue
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
        ExactOnlineClient $client,
        InvoiceService $invoiceService,
    ): void {
        if (! $client->isConnected()) {
            $this->invoice->updateQuietly([
                'exact_sync_error' => 'Exact Online is niet gekoppeld.',
            ]);

            ExactSyncLogger::failed($this->invoice, 'push_invoice', 'Exact Online is niet gekoppeld.');

            return;
        }

        $lock = Cache::lock("exact-invoice-{$this->invoice->id}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->invoice->refresh();

            if (filled($this->invoice->exact_invoice_id)) {
                return;
            }

            $this->invoice->updateQuietly([
                'exact_sync_error' => null,
            ]);

            $invoiceService->refreshAmounts($this->invoice);

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
                'status' => InvoiceStatus::SENT,
                'sent_at' => now(),
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
                'push_invoice',
                sprintf('Factuur geboekt als %s.', $result->documentNumber ?? $result->invoiceId),
            );
        } catch (ExactApiException $exception) {
            $this->invoice->updateQuietly([
                'exact_sync_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->invoice, 'push_invoice', $exception->getMessage());

            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
