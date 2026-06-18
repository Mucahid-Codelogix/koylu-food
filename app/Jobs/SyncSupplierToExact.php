<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSupplierSyncService;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSupplierToExact implements ShouldQueue
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

    public function __construct(public Supplier $supplier) {}

    public function handle(ExactSupplierSyncService $syncService, ExactOnlineClient $client): void
    {
        if (! $client->isConnected()) {
            $this->supplier->updateQuietly([
                'exact_sync_error' => 'Exact Online is niet gekoppeld.',
            ]);

            ExactSyncLogger::failed($this->supplier, 'push_supplier', 'Exact Online is niet gekoppeld.');

            return;
        }

        try {
            $accountId = $syncService->sync($this->supplier);

            $this->supplier->updateQuietly([
                'exact_account_id' => $accountId,
                'exact_synced_at' => now(),
                'exact_sync_error' => null,
            ]);

            ExactSyncLogger::success($this->supplier, 'push_supplier', 'Leverancier gesynchroniseerd.');
        } catch (ExactApiException $exception) {
            $this->supplier->updateQuietly([
                'exact_sync_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->supplier, 'push_supplier', $exception->getMessage());

            throw $exception;
        }
    }
}
