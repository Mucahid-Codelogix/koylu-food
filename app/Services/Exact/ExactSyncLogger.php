<?php

namespace App\Services\Exact;

use App\Models\ExactSyncLog;
use Illuminate\Database\Eloquent\Model;

class ExactSyncLogger
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public static function success(Model $syncable, string $action, ?string $message = null): ExactSyncLog
    {
        return self::record($syncable, $action, self::STATUS_SUCCESS, $message);
    }

    public static function failed(Model $syncable, string $action, string $error, ?string $message = null): ExactSyncLog
    {
        return self::record($syncable, $action, self::STATUS_FAILED, $message, $error);
    }

    public static function record(
        Model $syncable,
        string $action,
        string $status,
        ?string $message = null,
        ?string $error = null,
    ): ExactSyncLog {
        return ExactSyncLog::query()->create([
            'syncable_type' => $syncable->getMorphClass(),
            'syncable_id' => $syncable->getKey(),
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'error' => $error,
        ]);
    }
}
