<?php

use App\Jobs\SyncCustomerToExact;
use App\Mail\ExactSyncFailureMail;
use App\Models\Customer;
use App\Models\ExactSyncLog;
use App\Services\AdminDashboardService;
use App\Services\Exact\ExactSyncFailureNotifier;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'exact.alerts.mail_to' => 'admin@example.com',
        'exact.alerts.failure_threshold' => 3,
    ]);

    Mail::fake();
    Cache::flush();
});

it('sends a mail after repeated sync failures reach the threshold', function () {
    $customer = Customer::factory()->create();

    foreach (range(1, 3) as $attempt) {
        ExactSyncLogger::failed($customer, 'push_customer', 'Fout '.$attempt);
    }

    Mail::assertSent(ExactSyncFailureMail::class, function (ExactSyncFailureMail $mail): bool {
        return $mail->mailSubject === 'Exact sync blijft falen'
            && str_contains($mail->summary, '3 keer mislukt');
    });

    Mail::assertSentCount(1);
});

it('does not send duplicate alert mails within the cooldown window', function () {
    $customer = Customer::factory()->create();

    foreach (range(1, 4) as $attempt) {
        ExactSyncLogger::failed($customer, 'push_customer', 'Fout '.$attempt);
    }

    Mail::assertSentCount(1);
});

it('does not send mail when alert recipient is not configured', function () {
    config(['exact.alerts.mail_to' => null]);

    $customer = Customer::factory()->create();

    foreach (range(1, 3) as $attempt) {
        ExactSyncLogger::failed($customer, 'push_customer', 'Fout '.$attempt);
    }

    Mail::assertNothingSent();
});

it('sends a mail when an exact queue job fails definitively', function () {
    $job = Mockery::mock(Job::class, function (MockInterface $mock): void {
        $mock->shouldReceive('resolveName')->andReturn(SyncCustomerToExact::class);
        $mock->shouldReceive('uuid')->andReturn('test-job-uuid');
        $mock->shouldReceive('getQueue')->andReturn('default');
    });

    $event = new JobFailed(
        connectionName: 'database',
        job: $job,
        exception: new RuntimeException('Queue job crashed'),
    );

    ExactSyncFailureNotifier::notifyQueueJobFailed($event);

    Mail::assertSent(ExactSyncFailureMail::class, function (ExactSyncFailureMail $mail): bool {
        return $mail->mailSubject === 'Exact queue job mislukt';
    });
});

it('ignores non-exact queue job failures', function () {
    $job = Mockery::mock(Job::class, function (MockInterface $mock): void {
        $mock->shouldReceive('resolveName')->andReturn('App\\Jobs\\SomeOtherJob');
        $mock->shouldReceive('uuid')->andReturn('other-job-uuid');
    });

    $event = new JobFailed(
        connectionName: 'database',
        job: $job,
        exception: new RuntimeException('Other failure'),
    );

    ExactSyncFailureNotifier::notifyQueueJobFailed($event);

    Mail::assertNothingSent();
});

it('counts failed exact queue jobs on the admin dashboard', function () {
    ExactSyncLog::query()->create([
        'syncable_type' => Customer::class,
        'syncable_id' => Customer::factory()->create()->id,
        'action' => 'push_customer',
        'status' => ExactSyncLogger::STATUS_FAILED,
        'error' => 'Test',
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => SyncCustomerToExact::class]),
        'exception' => 'RuntimeException: test',
        'failed_at' => now(),
    ]);

    $data = app(AdminDashboardService::class)->getData();

    expect($data['exact']['failed_queue_jobs_count'])->toBe(1);
});
