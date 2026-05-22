<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;

class ProductPricingService
{
    public function pricePerKg(?Customer $customer, ProductSupplier $productSupplier): float
    {
        if ($customer !== null) {
            $customPrice = $customer->productPrices()
                ->where('product_supplier_id', $productSupplier->id)
                ->value('price');

            if ($customPrice !== null) {
                return (float) $customPrice;
            }
        }

        return (float) $productSupplier->price_per_kg;
    }

    public function unitPricePerPackaging(
        ?Customer $customer,
        ProductSupplier $productSupplier,
        ProductPackaging $packaging,
    ): float {
        $pricePerKg = $this->pricePerKg($customer, $productSupplier);

        return (float) bcmul((string) $pricePerKg, (string) $packaging->weight_kg, 2);
    }

    public function lineSubtotal(
        ?Customer $customer,
        ProductSupplier $productSupplier,
        ProductPackaging $packaging,
        float|int|string $quantity,
    ): float {
        $unitPrice = $this->unitPricePerPackaging($customer, $productSupplier, $packaging);

        return round($unitPrice * (float) $quantity, 2);
    }

    public function lineSubtotalForGramVariant(
        ?Customer $customer,
        ProductSupplier $productSupplier,
        ProductGramVariant $gramVariant,
        float|int|string $boxQuantity,
    ): float {
        $totalWeightKg = $gramVariant->calculateTotalWeightKg($boxQuantity);
        $pricePerKg = $this->pricePerKg($customer, $productSupplier);

        return round($totalWeightKg * $pricePerKg, 2);
    }

    public function unitPricePerBoxForGramVariant(
        ?Customer $customer,
        ProductSupplier $productSupplier,
        ProductGramVariant $gramVariant,
    ): float {
        return $this->lineSubtotalForGramVariant($customer, $productSupplier, $gramVariant, 1);
    }

    public function totalWeightKg(ProductPackaging $packaging, float|int|string $quantity): float
    {
        return round((float) $packaging->weight_kg * (float) $quantity, 3);
    }
}
