<?php

namespace App\Console\Commands;

use App\Models\ExactToken;
use App\Services\Exact\ExactApiException;
use App\Services\Exact\ExactOnlineClient;
use App\Services\Exact\ExactSyncLogger;
use Illuminate\Console\Command;

/**
 * Houdt de Exact-koppeling levend.
 *
 * Exact geeft bij elke vernieuwing een nieuwe refresh-token; blijft die te lang
 * ongebruikt, dan verloopt hij en moet er handmatig opnieuw gekoppeld worden.
 * Door dagelijks een lichte call te doen blijft de koppeling in stand.
 */
class RefreshExactConnection extends Command
{
    protected $signature = 'exact:refresh-connection';

    protected $description = 'Vernieuwt de Exact Online-tokens zodat de koppeling actief blijft';

    public function handle(ExactOnlineClient $client): int
    {
        if (! $client->isConnected()) {
            $this->components->warn('Exact Online is niet gekoppeld; niets te vernieuwen.');

            return self::SUCCESS;
        }

        try {
            $result = $client->testConnection();
        } catch (ExactApiException $exception) {
            $this->components->error('Vernieuwen mislukt: '.$exception->getMessage());

            if ($token = ExactToken::stored()) {
                ExactSyncLogger::failed($token, 'refresh_connection', $exception->getMessage());
            }

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Exact-koppeling vernieuwd (administratie %s).',
            $result['division'] ?? 'onbekend',
        ));

        return self::SUCCESS;
    }
}
