@php
    $syncErrors = $exact['sync_errors_count'] ?? 0;
    $failedLogs = $exact['failed_logs_count'] ?? 0;
    $failedQueueJobs = $exact['failed_queue_jobs_count'] ?? 0;
@endphp

@if ($syncErrors > 0 || $failedLogs > 0 || $failedQueueJobs > 0)
    <x-koylu.alert>
        <div class="flex items-start gap-2.5 min-w-0">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 mt-0.5" />
            <p class="text-sm">
                @if ($syncErrors > 0)
                    <span class="font-semibold">{{ $syncErrors }} open sync-fout{{ $syncErrors === 1 ? '' : 'en' }}</span>
                @endif
                @if ($syncErrors > 0 && ($failedLogs > 0 || $failedQueueJobs > 0))
                    <span class="opacity-60"> · </span>
                @endif
                @if ($failedLogs > 0)
                    <span>{{ $failedLogs }} mislukte sync{{ $failedLogs === 1 ? '' : 's' }} (24u)</span>
                @endif
                @if ($failedLogs > 0 && $failedQueueJobs > 0)
                    <span class="opacity-60"> · </span>
                @endif
                @if ($failedQueueJobs > 0)
                    <span>{{ $failedQueueJobs }} mislukte queue-job{{ $failedQueueJobs === 1 ? '' : 's' }} (24u)</span>
                @endif
            </p>
        </div>
        <div class="flex shrink-0 gap-3">
            @if ($failedLogs > 0)
                <a href="{{ $this->exactSyncLogsUrl() }}" class="koylu-link">Sync-log →</a>
            @endif
            @if ($failedQueueJobs > 0)
                <a href="{{ $this->failedQueueJobsUrl() }}" class="koylu-link">Mislukte jobs →</a>
            @endif
        </div>
    </x-koylu.alert>
@endif
