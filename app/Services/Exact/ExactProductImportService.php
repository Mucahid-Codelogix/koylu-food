<?php

namespace App\Services\Exact;

use App\Models\Product;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\Item;

class ExactProductImportService
{
    public function __construct(private ExactOnlineClient $client) {}

    public function import(): ExactCustomerImportResult
    {
        return $this->client->call(function (Connection $connection): ExactCustomerImportResult {
            $result = new ExactCustomerImportResult;
            $item = new Item($connection);

            $select = implode(',', [
                'ID',
                'Code',
                'Description',
                'SalesVatCode',
                'Unit',
                'IsSalesItem',
                'StandardSalesPrice',
            ]);

            try {
                $items = $item->filterAsGenerator('IsSalesItem eq true', '', $select);
            } catch (ApiException $exception) {
                throw ExactApiException::fromPicqer($exception);
            }

            foreach ($items as $exactItem) {
                if (! filled($exactItem->Code) || ! filled($exactItem->Description)) {
                    $result->skipped++;

                    continue;
                }

                $this->importItem($exactItem, $result);
            }

            return $result;
        });
    }

    private function importItem(Item $exactItem, ExactCustomerImportResult $result): void
    {
        $attributes = ExactItemToProductMapper::toProductAttributes($exactItem);
        $existing = $this->findExistingProduct($exactItem);

        if ($existing === null) {
            Product::withoutEvents(function () use ($attributes): void {
                Product::query()->create($attributes);
            });

            $result->created++;

            return;
        }

        $existing->updateQuietly($attributes);
        $result->updated++;
    }

    private function findExistingProduct(Item $exactItem): ?Product
    {
        $code = (string) $exactItem->Code;

        $byCode = Product::query()
            ->where('exact_article_code', $code)
            ->first();

        if ($byCode !== null) {
            return $byCode;
        }

        if ($productId = ExactItemMapper::productIdFromArticleCode($code)) {
            $byGeneratedCode = Product::query()->find($productId);

            if ($byGeneratedCode !== null) {
                return $byGeneratedCode;
            }
        }

        return Product::query()
            ->where('name', (string) $exactItem->Description)
            ->whereNull('exact_article_code')
            ->first();
    }
}
