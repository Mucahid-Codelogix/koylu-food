<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;

class OrderItemSnapshotBuilder
{
    public function __construct(
        protected ProductPricingService $pricing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromStandardLine(
        Product $product,
        ProductPackaging $packaging,
        ProductSupplier $productSupplier,
        float $boxQuantity,
        ?Customer $customer = null,
    ): array {
        $pricePerKg = $this->pricing->pricePerKg($customer, $productSupplier);
        $unitPrice = $this->pricing->unitPricePerPackaging($customer, $productSupplier, $packaging);
        $totalWeightKg = $this->pricing->totalWeightKg($packaging, $boxQuantity);

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => ProductType::Standard,
            'product_packaging_id' => $packaging->id,
            'product_supplier_id' => $productSupplier->id,
            'product_gram_variant_id' => null,
            'supplier_id' => $productSupplier->supplier_id,
            'supplier_name' => $productSupplier->supplier->name,
            'unit' => $packaging->displayLabel(),
            'packaging_label' => $packaging->displayLabel(),
            'quantity' => $boxQuantity,
            'weight_grams' => null,
            'pieces_per_box' => null,
            'box_weight_kg' => $packaging->weight_kg,
            'price_per_kg' => $pricePerKg,
            'ordered_pieces' => null,
            'ordered_total_weight_kg' => $totalWeightKg,
            'unit_price' => $unitPrice,
            'subtotal' => $this->pricing->lineSubtotal($customer, $productSupplier, $packaging, $boxQuantity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromWholeChickenLine(
        Product $product,
        ProductGramVariant $gramVariant,
        ProductSupplier $productSupplier,
        float $boxQuantity,
        ?Customer $customer = null,
    ): array {
        $pricePerKg = $this->pricing->pricePerKg($customer, $productSupplier);
        $unitPrice = $this->pricing->unitPricePerBoxForGramVariant($customer, $productSupplier, $gramVariant);
        $orderedPieces = $gramVariant->calculateOrderedPieces($boxQuantity);
        $totalWeightKg = $gramVariant->calculateTotalWeightKg($boxQuantity);
        $unitLabel = sprintf('Doos %s', $gramVariant->displayLabel());

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => ProductType::WholeChicken,
            'product_packaging_id' => null,
            'product_supplier_id' => $productSupplier->id,
            'product_gram_variant_id' => $gramVariant->id,
            'supplier_id' => $productSupplier->supplier_id,
            'supplier_name' => $productSupplier->supplier->name,
            'unit' => $unitLabel,
            'packaging_label' => $gramVariant->boxDescription(),
            'quantity' => $boxQuantity,
            'weight_grams' => $gramVariant->weight_grams,
            'pieces_per_box' => $gramVariant->pieces_per_box,
            'box_weight_kg' => $gramVariant->box_weight_kg,
            'price_per_kg' => $pricePerKg,
            'ordered_pieces' => $orderedPieces,
            'ordered_total_weight_kg' => $totalWeightKg,
            'unit_price' => $unitPrice,
            'subtotal' => $this->pricing->lineSubtotalForGramVariant($customer, $productSupplier, $gramVariant, $boxQuantity),
        ];
    }
}
