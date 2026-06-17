<?php

namespace App\Services\Exact;

use App\Models\ExactToken;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\Me;

class ExactOnlineClient
{
    private ?Lock $tokenRefreshLock = null;

    public function isConnected(): bool
    {
        $token = ExactToken::stored();

        return $token !== null && filled($token->refresh_token);
    }

    public function authorizationUrl(): string
    {
        $connection = $this->buildConnection();

        $state = Str::random(40);
        session(['exact_oauth_state' => $state]);
        $connection->setState($state);

        return $connection->getAuthUrl();
    }

    public function handleAuthorizationCallback(string $code, string $state): void
    {
        $expectedState = session()->pull('exact_oauth_state');

        if (! is_string($expectedState) || ! hash_equals($expectedState, $state)) {
            throw new ExactApiException('Ongeldige OAuth state.');
        }

        $connection = $this->buildConnection();
        $connection->setAuthorizationCode($code);

        try {
            $connection->connect();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        $this->syncCurrentDivision($connection);
    }

    public function disconnect(): void
    {
        ExactToken::query()->delete();
    }

    /**
     * @return array{division: int|null, full_name: string|null, email: string|null}
     */
    public function testConnection(): array
    {
        return $this->call(function (Connection $connection): array {
            $me = (new Me($connection))->findWithSelect('CurrentDivision,FullName,Email');
            $division = isset($me->CurrentDivision) ? (int) $me->CurrentDivision : null;

            if ($division !== null) {
                $this->persistDivision($division);
            }

            return [
                'division' => $division,
                'full_name' => $me->FullName ?? null,
                'email' => $me->Email ?? null,
            ];
        });
    }

    public function connection(): Connection
    {
        if (! $this->isConnected()) {
            throw new ExactApiException('Exact Online is niet gekoppeld.');
        }

        $connection = $this->buildConnection();
        $this->hydrateConnectionFromStorage($connection);

        try {
            $connection->checkOrAcquireAccessToken();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        return $connection;
    }

    /**
     * @template TReturn
     *
     * @param  callable(Connection): TReturn  $callback
     * @return TReturn
     */
    public function call(callable $callback): mixed
    {
        $maxAttempts = (int) config('exact.retry.max_attempts', 3);
        $delaySeconds = (int) config('exact.retry.initial_delay_seconds', 1);
        $attempt = 0;

        while (true) {
            try {
                return $callback($this->connection());
            } catch (ExactApiException $exception) {
                $attempt++;

                if ($attempt >= $maxAttempts || ! $this->shouldRetry($exception)) {
                    throw $exception;
                }

                sleep($delaySeconds);
                $delaySeconds *= 2;
            }
        }
    }

    private function shouldRetry(ExactApiException $exception): bool
    {
        return in_array($exception->statusCode, [429, 500, 502, 503, 504], true);
    }

    private function buildConnection(): Connection
    {
        $connection = new Connection;
        $connection->setExactClientId((string) config('exact.client_id'));
        $connection->setExactClientSecret((string) config('exact.client_secret'));
        $connection->setRedirectUrl((string) config('exact.redirect_uri'));
        $connection->setBaseUrl((string) config('exact.base_url'));

        if ($division = config('exact.division')) {
            $connection->setDivision((int) $division);
        }

        $this->registerTokenCallbacks($connection);

        return $connection;
    }

    private function registerTokenCallbacks(Connection $connection): void
    {
        $connection->setTokenUpdateCallback(function (Connection $connection): void {
            $this->persistTokens($connection);
        });

        $connection->setRefreshAccessTokenCallback(function (Connection $connection): void {
            $this->hydrateConnectionFromStorage($connection);
        });

        $connection->setAcquireAccessTokenLockCallback(function (): void {
            $this->tokenRefreshLock = Cache::lock('exact-token-refresh', 30);
            $this->tokenRefreshLock->block(10);
        });

        $connection->setAcquireAccessTokenUnlockCallback(function (): void {
            $this->tokenRefreshLock?->release();
            $this->tokenRefreshLock = null;
        });
    }

    private function persistTokens(Connection $connection): void
    {
        ExactToken::storeOrUpdate([
            'access_token' => (string) $connection->getAccessToken(),
            'refresh_token' => (string) $connection->getRefreshToken(),
            'expires_at' => CarbonImmutable::createFromTimestamp($connection->getTokenExpires()),
            'division' => $connection->getDivision() ?? config('exact.division'),
        ]);
    }

    private function syncCurrentDivision(Connection $connection): void
    {
        try {
            $me = (new Me($connection))->findWithSelect('CurrentDivision');
        } catch (ApiException $exception) {
            return;
        }

        if (! isset($me->CurrentDivision)) {
            return;
        }

        $this->persistDivision((int) $me->CurrentDivision);
    }

    private function persistDivision(int $division): void
    {
        $token = ExactToken::stored();

        if ($token === null) {
            return;
        }

        ExactToken::storeOrUpdate([
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_at' => $token->expires_at,
            'division' => $division,
        ]);
    }

    private function hydrateConnectionFromStorage(Connection $connection): void
    {
        $token = ExactToken::stored();

        if ($token === null) {
            return;
        }

        $connection->setAccessToken($token->access_token);
        $connection->setRefreshToken($token->refresh_token);
        $connection->setTokenExpires($token->expires_at->timestamp);

        if ($token->division !== null) {
            $connection->setDivision($token->division);
        }
    }
}
