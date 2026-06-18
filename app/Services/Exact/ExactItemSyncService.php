<?php

namespace App\Services\Exact;

use App\Models\Product;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\Item;

class ExactItemSyncService
{
    public function __construct(private ExactOnlineClient $client) {}

    public function sync(Product $product): string
    {
        return $this->client->call(function (Connection $connection) use ($product): string {
            $payload = ExactItemMapper::toExactItem($product);
            $item = new Item($connection);
            $code = ExactItemMapper::articleCode($product);

            $existing = $this->findByCode($item, $code);

            if ($existing !== null) {
                return $this->updateItem($item, $existing, $payload);
            }

            return $this->createItem($item, $payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createItem(Item $item, array $payload): string
    {
        foreach ($payload as $field => $value) {
            $item->{$field} = $value;
        }

        try {
            $item->save();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        if (! filled($item->ID)) {
            throw new ExactApiException('Exact heeft geen artikel-ID teruggegeven na aanmaken.');
        }

        return (string) $item->ID;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateItem(Item $item, string $itemId, array $payload): string
    {
        $item->ID = $itemId;

        foreach ($payload as $field => $value) {
            if ($field === 'Code') {
                continue;
            }

            $item->{$field} = $value;
        }

        try {
            $item->update();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        return $itemId;
    }

    private function findByCode(Item $item, string $code): ?string
    {
        $escapedCode = str_replace("'", "''", $code);

        try {
            $results = $item->filter("Code eq '{$escapedCode}'", '', 'ID', ['$top' => 1]);
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        if ($results === [] || ! isset($results[0]->ID)) {
            return null;
        }

        return (string) $results[0]->ID;
    }
}
