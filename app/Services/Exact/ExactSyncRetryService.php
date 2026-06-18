<?php

namespace App\Services\Exact;

use App\Jobs\PushInvoiceToExact;
use App\Jobs\SyncCustomerToExact;
use App\Jobs\SyncProductToExact;
use App\Jobs\SyncSupplierToExact;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use DomainException;
use Illuminate\Database\Eloquent\Model;

class ExactSyncRetryService
{
    public function __construct(
        protected ExactOnlineClient $client,
    ) {}

    public function canRetry(ExactSyncLog $log): bool
    {
        if ($log->status !== ExactSyncLogger::STATUS_FAILED) {
            return false;
        }

        if (! $this->client->isConnected()) {
            return false;
        }

        if (! $log->syncable instanceof Model) {
            return false;
        }

        return in_array($log->action, [
            'push_customer',
            'push_product',
            'push_supplier',
            'push_invoice',
        ], true);
    }

    public function retry(ExactSyncLog $log): void
    {
        if (! $this->canRetry($log)) {
            throw new DomainException('Deze sync kan niet opnieuw worden geprobeerd.');
        }

        /** @var Customer|Product|Supplier|Invoice $syncable */
        $syncable = $log->syncable;

        $syncable->updateQuietly(['exact_sync_error' => null]);

        match ($log->action) {
            'push_customer' => SyncCustomerToExact::dispatch($syncable),
            'push_product' => SyncProductToExact::dispatch($syncable),
            'push_supplier' => SyncSupplierToExact::dispatch($syncable),
            'push_invoice' => PushInvoiceToExact::dispatch($syncable),
            default => throw new DomainException('Onbekende sync-actie.'),
        };
    }
}
