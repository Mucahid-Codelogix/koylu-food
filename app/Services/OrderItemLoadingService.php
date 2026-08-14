<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\ProductGramVariant;

class OrderItemLoadingService
{
    public function recordActualWeight(OrderItem $orderItem, ?float $actualWeightKg): void
    {
        $orderItem->update(['loaded_actual_weight_kg' => $actualWeightKg > 0 ? $actualWeightKg : null]);
    }

    /**
     * Leg vast hoeveel verpakkingen (dozen, bakjes, kratten) er werkelijk geladen zijn.
     * Een leeg of ongeldig aantal betekent: geladen zoals besteld.
     */
    public function recordLoadedQuantity(OrderItem $orderItem, ?float $loadedQuantity): OrderItem
    {
        $orderedQuantity = (float) $orderItem->quantity;
        $isCorrection = $loadedQuantity !== null
            && $loadedQuantity >= 0
            && abs($loadedQuantity - $orderedQuantity) > 0.001;

        $attributes = [
            'loaded_packaging_quantity' => $isCorrection ? $loadedQuantity : null,
            'loaded_at' => $orderItem->loaded_at ?? now(),
        ];

        if ($orderItem->isWholeChicken()) {
            $variant = $orderItem->loadedGramVariant ?? $orderItem->productGramVariant;
            $effectiveQuantity = $isCorrection ? $loadedQuantity : $orderedQuantity;

            if ($variant !== null) {
                $attributes['loaded_pieces'] = $variant->calculateOrderedPieces($effectiveQuantity);
                $attributes['loaded_total_weight_kg'] = $variant->calculateTotalWeightKg($effectiveQuantity);
            }
        }

        $orderItem->update($attributes);

        return $orderItem->fresh();
    }

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
