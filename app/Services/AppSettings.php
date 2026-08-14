<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Applicatie-brede instellingen die de admin zelf kan beheren.
 */
class AppSettings
{
    public const CRATE_DEPOSIT_PRICE = 'crate_deposit_price';

    private const CACHE_KEY = 'app_settings';

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );

        $this->flush();
    }

    public function crateDepositPrice(): float
    {
        return (float) $this->get(self::CRATE_DEPOSIT_PRICE, 0);
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => AppSetting::query()->pluck('value', 'key')->all(),
        );
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
