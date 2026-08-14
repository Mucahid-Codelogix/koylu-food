<?php

namespace App\Services\Exact;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Picqer\Financials\Exact\Connection;
use SimpleXMLElement;
use Throwable;

/**
 * Toegang tot de XML API van Exact Online.
 *
 * Deze API is legacy, maar is het enige kanaal voor handelingen die de REST API
 * niet kent — zoals het afletteren van betalingen tegen facturen. Hij gebruikt
 * hetzelfde OAuth-token als de REST-koppeling.
 *
 * Twee eigenaardigheden om te onthouden:
 * 1. De XML moet als **rauwe body** mee, met een lege `Expect`-header; met
 *    `Expect: 100-continue` blijft de verbinding hangen.
 * 2. Exact antwoordt **altijd met status 200**, ook bij fouten. De werkelijke
 *    uitslag staat in het `<Messages>`-blok van de respons.
 */
class ExactXmlClient
{
    public function __construct(
        private ExactOnlineClient $client,
    ) {}

    /**
     * Upload XML naar een topic en geef de meldingen van Exact terug.
     *
     * Let op: ook een geslaagde upload levert meldingen op. Beoordeel het
     * resultaat op `isError()`, niet op het aantal meldingen.
     *
     * @return array<int, ExactXmlMessage>
     */
    public function upload(string $topic, string $xml): array
    {
        $context = $this->client->call(fn (Connection $connection): array => [
            'token' => (string) $connection->getAccessToken(),
            'division' => $connection->getDivision() ?? config('exact.division'),
        ]);

        $url = sprintf(
            '%s/docs/XMLUpload.aspx?Topic=%s&_Division_=%s',
            rtrim((string) config('exact.base_url'), '/'),
            urlencode($topic),
            urlencode((string) $context['division']),
        );

        $response = Http::withToken($context['token'])
            ->withHeaders(['Expect' => ''])
            ->withBody($xml, 'text/xml')
            ->timeout((int) config('exact.xml.timeout_seconds', 60))
            ->post($url);

        if ($response->failed()) {
            throw new ExactApiException(sprintf(
                'XML-upload naar Exact mislukt (HTTP %d).',
                $response->status(),
            ));
        }

        Log::debug('Exact XML-antwoord', ['topic' => $topic, 'body' => $response->body()]);

        return $this->messages($response->body());
    }

    /**
     * @return array<int, ExactXmlMessage>
     */
    private function messages(string $body): array
    {
        $body = ltrim($body, "\xEF\xBB\xBF \t\n\r");

        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable $exception) {
            throw new ExactApiException('Onleesbaar antwoord van de XML API van Exact.');
        }

        $messages = [];

        foreach ($xml->Messages->Message ?? [] as $message) {
            $description = trim((string) $message->Description);

            if ($description !== '') {
                $messages[] = new ExactXmlMessage(
                    type: (int) $message['type'],
                    description: $description,
                );
            }
        }

        return $messages;
    }
}
