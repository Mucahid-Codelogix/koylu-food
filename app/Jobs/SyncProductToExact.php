<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactItemMapper;
use App\Services\Exact\ExactItemSyncService;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToExact implements ShouldQueue
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

    public function __construct(public Product $product) {}

    public function handle(ExactItemSyncService $syncService, ExactOnlineClient $client): void
    {
        if (! $client->isConnected()) {
            $this->product->updateQuietly([
                'exact_sync_error' => 'Exact Online is niet gekoppeld.',
            ]);

            ExactSyncLogger::failed($this->product, 'push_product', 'Exact Online is niet gekoppeld.');

            return;
        }

        try {
            $syncService->sync($this->product);

            $this->product->updateQuietly([
                'exact_article_code' => ExactItemMapper::articleCode($this->product),
                'exact_synced_at' => now(),
                'exact_sync_error' => null,
            ]);

            ExactSyncLogger::success($this->product, 'push_product', 'Artikel gesynchroniseerd.');
        } catch (ExactApiException $exception) {
            $this->product->updateQuietly([
                'exact_sync_error' => $exception->getMessage(),
            ]);

            ExactSyncLogger::failed($this->product, 'push_product', $exception->getMessage());

            throw $exception;
        }
    }
}
