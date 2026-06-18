<?php

namespace App\Services\Exact;

use App\Jobs\ImportCustomersFromExact;
use App\Jobs\ImportProductsFromExact;
use App\Jobs\ImportSuppliersFromExact;
use App\Jobs\PushInvoiceToExact;
use App\Jobs\SyncCustomerToExact;
use App\Jobs\SyncProductToExact;
use App\Jobs\SyncSupplierToExact;
use App\Mail\ExactSyncFailureMail;
use App\Models\ExactSyncLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class ExactSyncFailureNotifier
{
    /**
     * @var list<class-string>
     */
    private const EXACT_QUEUE_JOBS = [
        SyncCustomerToExact::class,
        SyncProductToExact::class,
        SyncSupplierToExact::class,
        PushInvoiceToExact::class,
        ImportCustomersFromExact::class,
        ImportProductsFromExact::class,
        ImportSuppliersFromExact::class,
    ];

    public static function maybeNotifyRepeatedSyncFailure(Model $syncable, string $action, string $error): void
    {
        $mailTo = config('exact.alerts.mail_to');

        if (blank($mailTo)) {
            return;
        }

        $threshold = (int) config('exact.alerts.failure_threshold', 3);

        $recentFailures = ExactSyncLog::query()
            ->where('syncable_type', $syncable->getMorphClass())
            ->where('syncable_id', $syncable->getKey())
            ->where('action', $action)
            ->where('status', ExactSyncLogger::STATUS_FAILED)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentFailures < $threshold) {
            return;
        }

        $cacheKey = sprintf(
            'exact-sync-alert:%s:%s:%s',
            $syncable->getMorphClass(),
            $syncable->getKey(),
            $action,
        );

        if (! Cache::add($cacheKey, true, now()->addDay())) {
            return;
        }

        Mail::to($mailTo)->send(new ExactSyncFailureMail(
            mailSubject: 'Exact sync blijft falen',
            headline: 'Herhaalde Exact sync-fout',
            summary: sprintf(
                '%s #%s (%s) is %d keer mislukt in 24 uur.',
                class_basename($syncable),
                $syncable->getKey(),
                $action,
                $recentFailures,
            ),
            error: $error,
        ));
    }

    public static function notifyQueueJobFailed(JobFailed $event): void
    {
        $mailTo = config('exact.alerts.mail_to');

        if (blank($mailTo)) {
            return;
        }

        $jobName = $event->job->resolveName();

        if (! self::isExactQueueJob($jobName)) {
            return;
        }

        $cacheKey = 'exact-queue-alert:'.sha1($jobName.$event->job->uuid());

        if (! Cache::add($cacheKey, true, now()->addHours(6))) {
            return;
        }

        Mail::to($mailTo)->send(new ExactSyncFailureMail(
            mailSubject: 'Exact queue job mislukt',
            headline: 'Queue job definitief mislukt',
            summary: sprintf('Job %s op queue %s is definitief mislukt.', class_basename($jobName), $event->job->getQueue()),
            error: (string) $event->exception->getMessage(),
        ));
    }

    private static function isExactQueueJob(string $jobName): bool
    {
        foreach (self::EXACT_QUEUE_JOBS as $exactJob) {
            if ($jobName === $exactJob || str_ends_with($jobName, class_basename($exactJob))) {
                return true;
            }
        }

        return false;
    }
}
