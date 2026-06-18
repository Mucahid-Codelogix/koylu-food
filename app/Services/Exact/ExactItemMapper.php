<?php

namespace App\Services\Exact;

use App\Enums\VatCategory;
use App\Models\Product;

class ExactItemMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toExactItem(Product $product): array
    {
        $payload = [
            'Code' => self::articleCode($product),
            'Description' => $product->name,
            'Unit' => (string) config('exact.item.unit', 'kg'),
            'IsSalesItem' => true,
            'IsStockItem' => false,
            'IsPurchaseItem' => false,
        ];

        if ($salesVatCode = self::salesVatCode($product)) {
            $payload['SalesVatCode'] = $salesVatCode;
        }

        $pricePerKg = $product->defaultProductSupplier()?->price_per_kg;

        if ($pricePerKg !== null) {
            $payload['StandardSalesPrice'] = (float) $pricePerKg;
        }

        if ($itemGroup = config('exact.item.item_group')) {
            $payload['ItemGroup'] = $itemGroup;
        }

        return $payload;
    }

    public static function articleCode(Product $product): string
    {
        if (filled($product->exact_article_code)) {
            return (string) $product->exact_article_code;
        }

        $prefix = (string) config('exact.item.code_prefix', 'KOYLU');

        return sprintf('%s-P-%d', $prefix, $product->id);
    }

    public static function productIdFromArticleCode(?string $articleCode): ?int
    {
        if (! filled($articleCode)) {
            return null;
        }

        $prefix = preg_quote((string) config('exact.item.code_prefix', 'KOYLU'), '/');

        if (! preg_match('/^'.$prefix.'-P-(\d+)$/', $articleCode, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    public static function salesVatCode(Product $product): ?string
    {
        $vatCategory = $product->vat_category ?? VatCategory::High;

        return match ($vatCategory) {
            VatCategory::Low => config('exact.item.vat_codes.low'),
            VatCategory::High => config('exact.item.vat_codes.high'),
        };
    }
}
