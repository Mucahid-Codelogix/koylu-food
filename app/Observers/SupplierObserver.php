<?php

namespace App\Observers;

use App\Jobs\SyncSupplierToExact;
use App\Models\Supplier;

class SupplierObserver
{
    /**
     * @var list<string>
     */
    private const EXACT_SYNC_FIELDS = [
        'exact_account_id',
        'exact_synced_at',
        'exact_sync_error',
    ];

    public function created(Supplier $supplier): void
    {
        if (filled($supplier->exact_account_id)) {
            return;
        }

        SyncSupplierToExact::dispatch($supplier);
    }

    public function updated(Supplier $supplier): void
    {
        if ($this->onlyExactSyncFieldsChanged($supplier)) {
            return;
        }

        SyncSupplierToExact::dispatch($supplier);
    }

    private function onlyExactSyncFieldsChanged(Supplier $supplier): bool
    {
        $changed = array_keys($supplier->getChanges());

        return $changed !== [] && array_diff($changed, self::EXACT_SYNC_FIELDS) === [];
    }
}
