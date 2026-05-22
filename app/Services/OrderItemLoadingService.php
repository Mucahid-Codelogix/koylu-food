<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\ProductGramVariant;

class OrderItemLoadingService
{
    public function recordLoadedVariant(
        OrderItem $orderItem,
        ProductGramVariant $loadedVariant,
        ?string $substitutionReason = null,
    ): OrderItem {
        $orderedVariantId = $orderItem->product_gram_variant_id;
        $boxQuantity = (float) ($orderItem->loaded_packaging_quantity ?? $orderItem->quantity);

        $wasSubstituted = $orderedVariantId !== null
            && $loadedVariant->id !== $orderedVariantId;

        $orderItem->update([
            'loaded_gram_variant_id' => $loadedVariant->id,
            'substituted_from_gram_variant_id' => $wasSubstituted ? $orderedVariantId : null,
            'loaded_weight_grams' => $loadedVariant->weight_grams,
            'loaded_pieces_per_box' => $loadedVariant->pieces_per_box,
            'loaded_box_weight_kg' => $loadedVariant->box_weight_kg,
            'loaded_packaging_quantity' => $boxQuantity,
            'loaded_total_weight_kg' => $loadedVariant->calculateTotalWeightKg($boxQuantity),
            'loaded_pieces' => $loadedVariant->calculateOrderedPieces($boxQuantity),
            'loading_substitution_reason' => $wasSubstituted ? $substitutionReason : null,
            'loaded_at' => now(),
        ]);

        return $orderItem->fresh(['loadedGramVariant', 'productGramVariant', 'substitutedFromGramVariant']);
    }

    public function initializeFromOrder(OrderItem $orderItem): OrderItem
    {
        if (! $orderItem->isWholeChicken() || $orderItem->loaded_at !== null) {
            return $orderItem;
        }

        $variant = $orderItem->productGramVariant;

        if ($variant === null) {
            return $orderItem;
        }

        return $this->recordLoadedVariant($orderItem, $variant);
    }
}
