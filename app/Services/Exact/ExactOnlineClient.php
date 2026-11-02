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
use Picqer\Financials\Exact\SystemDivision;

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

        $this->syncDivision($connection);
    }

    public function disconnect(): void
    {
        ExactToken::query()->delete();
    }

    /**
     * @return array{division: int, division_name: string|null, full_name: string|null, email: string|null}
     */
    public function testConnection(): array
    {
        return $this->call(function (Connection $connection): array {
            $me = (new Me($connection))->findWithSelect('FullName,Email');
            $division = (int) $connection->getDivision();
            $divisionName = $this->divisionName($connection, $division);

            $this->persistDivision($division, $divisionName);

            return [
                'division' => $division,
                'division_name' => $divisionName,
                'full_name' => $me->FullName ?? null,
                'email' => $me->Email ?? null,
            ];
        });
    }

    /**
     * De administratie uit EXACT_DIVISION is leidend. Zonder die instelling
     * weigert de koppeling te werken: anders zou Exact de administratie kiezen
     * die de gebruiker als laatste heeft geopend, en dat kan zomaar de
     * test-administratie zijn.
     */
    public function configuredDivision(): ?int
    {
        $division = config('exact.division');

        return filled($division) ? (int) $division : null;
    }

    public static function describeDivision(?int $division, ?string $divisionName): string
    {
        if ($division === null) {
            return 'onbekend';
        }

        return filled($divisionName) ? sprintf('%d – %s', $division, $divisionName) : (string) $division;
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

    /**
     * Exact gebruikt HTTP 500 ook voor inhoudelijke fouten ("Ongeldige
     * referentie: Eenheid"). Daarop opnieuw proberen helpt niet en maakt een
     * mislukte boeking alleen maar trager, dus we herhalen alleen bij fouten
     * die echt tijdelijk zijn.
     */
    private function shouldRetry(ExactApiException $exception): bool
    {
        return in_array($exception->statusCode, [429, 502, 503, 504], true);
    }

    private function buildConnection(): Connection
    {
        $connection = app(Connection::class);
        $connection->setExactClientId((string) config('exact.client_id'));
        $connection->setExactClientSecret((string) config('exact.client_secret'));
        $connection->setRedirectUrl((string) config('exact.redirect_uri'));
        $connection->setBaseUrl((string) config('exact.base_url'));

        $connection->setDivision($this->requireDivision());

        $this->registerTokenCallbacks($connection);

        return $connection;
    }

    private function requireDivision(): int
    {
        return $this->configuredDivision()
            ?? throw new ExactApiException('EXACT_DIVISION is niet ingesteld. Stel eerst in welke Exact-administratie de app gebruikt.');
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
        $division = (int) $connection->getDivision();
        $storedToken = ExactToken::stored();

        ExactToken::storeOrUpdate([
            'access_token' => (string) $connection->getAccessToken(),
            'refresh_token' => (string) $connection->getRefreshToken(),
            'expires_at' => CarbonImmutable::createFromTimestamp($connection->getTokenExpires()),
            'division' => $division,
            'division_name' => $storedToken?->division === $division ? $storedToken?->division_name : null,
        ]);
    }

    private function syncDivision(Connection $connection): void
    {
        $division = (int) $connection->getDivision();

        $this->persistDivision($division, $this->divisionName($connection, $division));
    }

    /**
     * Exact geeft de naam alleen als het gekoppelde account toegang heeft tot
     * de administratie. Lukt het opvragen niet, dan blijft de naam leeg en
     * waarschuwt de koppelingspagina.
     */
    private function divisionName(Connection $connection, int $division): ?string
    {
        try {
            $divisions = (new SystemDivision($connection))->filter("Code eq {$division}", '', 'Code,Description');
        } catch (ApiException $exception) {
            return null;
        }

        $description = $divisions[0]->Description ?? null;

        return filled($description) ? (string) $description : null;
    }

    private function persistDivision(int $division, ?string $divisionName): void
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
            'division_name' => $divisionName,
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
    }
}
