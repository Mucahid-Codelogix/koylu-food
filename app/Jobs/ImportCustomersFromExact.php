<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactCustomerImportService;
use App\Services\Exact\ExactOnlineClient;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportCustomersFromExact implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $initiatedByUserId) {}

    public function handle(ExactCustomerImportService $importService, ExactOnlineClient $client): void
    {
        $user = User::query()->find($this->initiatedByUserId);

        if (! $client->isConnected()) {
            $this->notifyUser($user, 'Import mislukt', 'Exact Online is niet gekoppeld.', true);

            return;
        }

        try {
            $result = $importService->import();
        } catch (ExactApiException $exception) {
            $this->notifyUser($user, 'Import mislukt', $exception->getMessage(), true);

            throw $exception;
        }

        $this->notifyUser(
            $user,
            'Klanten geïmporteerd uit Exact',
            $result->summary(),
            false,
        );
    }

    private function notifyUser(?User $user, string $title, string $body, bool $isDanger): void
    {
        if ($user === null) {
            return;
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body);

        if ($isDanger) {
            $notification->danger();
        } else {
            $notification->success();
        }

        try {
            $notification->sendToDatabase($user);
        } catch (Throwable $exception) {
            Log::warning('Exact import notification could not be stored.', [
                'user_id' => $user->id,
                'title' => $title,
                'body' => $body,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
