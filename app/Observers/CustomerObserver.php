<?php

namespace App\Observers;

use App\Jobs\SyncCustomerToExact;
use App\Models\Customer;

class CustomerObserver
{
    /**
     * @var list<string>
     */
    private const EXACT_SYNC_FIELDS = [
        'exact_account_id',
        'exact_synced_at',
        'exact_sync_error',
    ];

    public function created(Customer $customer): void
    {
        if (filled($customer->exact_account_id)) {
            return;
        }

        SyncCustomerToExact::dispatch($customer);
    }

    public function updated(Customer $customer): void
    {
        if ($this->onlyExactSyncFieldsChanged($customer)) {
            return;
        }

        SyncCustomerToExact::dispatch($customer);
    }

    private function onlyExactSyncFieldsChanged(Customer $customer): bool
    {
        $changed = array_keys($customer->getChanges());

        return $changed !== [] && array_diff($changed, self::EXACT_SYNC_FIELDS) === [];
    }
}
