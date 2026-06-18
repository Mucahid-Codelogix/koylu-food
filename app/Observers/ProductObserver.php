<?php

namespace App\Observers;

use App\Jobs\SyncProductToExact;
use App\Models\Product;

class ProductObserver
{
    /**
     * @var list<string>
     */
    private const EXACT_SYNC_FIELDS = [
        'exact_article_code',
        'exact_synced_at',
        'exact_sync_error',
    ];

    public function created(Product $product): void
    {
        if (filled($product->exact_synced_at)) {
            return;
        }

        SyncProductToExact::dispatch($product);
    }

    public function updated(Product $product): void
    {
        if ($this->onlyExactSyncFieldsChanged($product)) {
            return;
        }

        SyncProductToExact::dispatch($product);
    }

    private function onlyExactSyncFieldsChanged(Product $product): bool
    {
        $changed = array_keys($product->getChanges());

        return $changed !== [] && array_diff($changed, self::EXACT_SYNC_FIELDS) === [];
    }
}
