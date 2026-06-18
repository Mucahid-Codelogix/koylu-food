<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\DeliveryItem;

class DeliveryDeviationSummary
{
    public static function html(?Delivery $delivery): ?string
    {
        if ($delivery === null) {
            return null;
        }

        $delivery->loadMissing('items.orderItem');

        $lines = $delivery->items
            ->filter(fn (DeliveryItem $item): bool => self::hasDeviation($item))
            ->map(fn (DeliveryItem $item): string => self::formatItem($item))
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        return $lines->implode('<br>');
    }

    public static function hasDeviation(DeliveryItem $item): bool
    {
        return (float) $item->delivered_quantity < (float) $item->ordered_quantity
            || filled($item->missed_reason)
            || filled($item->return_note);
    }

    public static function formatItem(DeliveryItem $item): string
    {
        $name = e($item->orderItem?->product_name ?? 'Product');
        $parts = [];

        if ((float) $item->delivered_quantity < (float) $item->ordered_quantity) {
            $parts[] = sprintf(
                'Geleverd: %s/%s',
                self::formatQuantity((float) $item->delivered_quantity),
                self::formatQuantity((float) $item->ordered_quantity),
            );
        }

        if (filled($item->missed_reason)) {
            $parts[] = e((string) $item->missed_reason);
        }

        if (filled($item->return_note)) {
            $parts[] = 'Retour: '.e((string) $item->return_note);
        }

        return $name.': '.implode(' — ', $parts);
    }

    private static function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }
}
