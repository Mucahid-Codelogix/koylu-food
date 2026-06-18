<?php

namespace App\Services\Exact;

use App\Enums\ProductType;
use App\Enums\VatCategory;
use Picqer\Financials\Exact\Item;

class ExactItemToProductMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toProductAttributes(Item $item): array
    {
        return [
            'name' => (string) $item->Description,
            'product_type' => ProductType::Standard,
            'min_order_quantity' => 1,
            'exact_article_code' => (string) $item->Code,
            'vat_category' => self::vatCategory($item),
            'exact_synced_at' => now(),
            'exact_sync_error' => null,
            'is_active' => true,
        ];
    }

    public static function vatCategory(Item $item): VatCategory
    {
        $lowCode = config('exact.item.vat_codes.low');

        if (filled($lowCode) && (string) ($item->SalesVatCode ?? '') === (string) $lowCode) {
            return VatCategory::Low;
        }

        return VatCategory::High;
    }
}
