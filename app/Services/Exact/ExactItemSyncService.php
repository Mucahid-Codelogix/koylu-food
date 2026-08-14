<?php

namespace App\Services\Exact;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\Item;

class ExactItemSyncService
{
    public function __construct(private ExactOnlineClient $client) {}

    /**
     * Zoek het Exact-artikel-ID bij een artikelcode. Wordt gebruikt voor
     * artikelen die niet uit onze productcatalogus komen, zoals statiegeld.
     */
    public function findItemIdByCode(string $code): ?string
    {
        return $this->client->call(
            fn (Connection $connection): ?string => $this->findByCode(new Item($connection), $code),
        );
    }

    /**
     * Het statiegeldartikel hoort niet in onze productcatalogus, maar moet wel
     * in Exact bestaan. Bestaat het nog niet, dan maken we het btw-vrij aan.
     */
    public function ensureCrateDepositItem(): string
    {
        $code = (string) config('exact.crate_deposit.article_code');

        if (blank($code)) {
            throw new ExactApiException(
                'Er is geen Exact-artikelcode voor statiegeld kratten ingesteld (EXACT_CRATE_DEPOSIT_ARTICLE_CODE).',
            );
        }

        /** Het statiegeldartikel verandert niet; onthouden scheelt een lookup per factuur. */
        return Cache::remember(
            "exact-crate-deposit-item-{$code}",
            now()->addDay(),
            fn (): string => $this->resolveCrateDepositItem($code),
        );
    }

    private function resolveCrateDepositItem(string $code): string
    {
        return $this->client->call(function (Connection $connection) use ($code): string {
            $item = new Item($connection);

            if ($existing = $this->findByCode($item, $code)) {
                return $existing;
            }

            $payload = [
                'Code' => $code,
                'Description' => (string) config('exact.crate_deposit.description', 'Statiegeld kratten'),
                'Unit' => (string) config('exact.crate_deposit.unit', 'st'),
                'IsSalesItem' => true,
                'IsStockItem' => false,
                'IsPurchaseItem' => false,
            ];

            if (filled($vatCode = config('exact.crate_deposit.vat_code'))) {
                $payload['SalesVatCode'] = (string) $vatCode;
            }

            if (filled($itemGroup = config('exact.item.item_group'))) {
                $payload['ItemGroup'] = $itemGroup;
            }

            return $this->createItem(new Item($connection), $payload);
        });
    }

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
