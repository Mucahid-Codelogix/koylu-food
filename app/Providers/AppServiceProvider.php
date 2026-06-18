<?php

namespace App\Providers;

use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncFailureNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExactOnlineClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->ensureApplicationStorageIsReady();
        $this->configureDefaults();
        $this->registerQueueFailureAlerts();
    }

    protected function registerQueueFailureAlerts(): void
    {
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            ExactSyncFailureNotifier::notifyQueueJobFailed($event);
        });
    }

    protected function ensureApplicationStorageIsReady(): void
    {
        if (config('filesystems.upload_disk') !== 'public') {
            return;
        }

        foreach ([
            storage_path('app/public/products'),
            storage_path('app/public/livewire-tmp'),
            storage_path('app/private/livewire-tmp'),
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }

        $publicStorageLink = public_path('storage');

        if (! file_exists($publicStorageLink)) {
            try {
                File::link(storage_path('app/public'), $publicStorageLink);
            } catch (\Throwable) {
                // Laravel serves /storage/* via the public disk when serve => true.
            }
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
