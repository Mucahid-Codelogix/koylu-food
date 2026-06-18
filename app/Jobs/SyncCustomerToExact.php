<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCustomerSyncService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCustomerToExact implements ShouldQueue
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

    public function __construct(public Customer $customer) {}

    public function handle(ExactCustomerSyncService $syncService, ExactOnlineClient $client): void
    {
        if (! $client->isConnected()) {
            $this->customer->updateQuietly([
                'exact_sync_error' => 'Exact Online is niet gekoppeld.',
            ]);

            ExactSyncLogger::failed($this->customer, 'push_customer', 'Exact Online is niet gekoppeld.');

            return;
        }

        try {
            $accountId = $syncService->sync($this->customer);

            $this->customer->updateQuietly([
                'exact_account_id' => $accountId,
                'exact_synced_at' => now(),
                'exact_sync_error' => null,
            ]);

            ExactSyncLogger::success($this->customer, 'push_customer', 'Debiteur gesynchroniseerd.');
        } catch (ExactApiException $exception) {
            $this->customer->updateQuietly([
                'exact_sync_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->customer, 'push_customer', $exception->getMessage());

            throw $exception;
        }
    }
}
