<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Observers\OrderItemObserver;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([OrderItemObserver::class])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'box_weight_kg' => 'decimal:3',
            'price_per_kg' => 'decimal:4',
            'ordered_pieces' => 'decimal:2',
            'ordered_total_weight_kg' => 'decimal:3',
            'loaded_box_weight_kg' => 'decimal:3',
            'loaded_packaging_quantity' => 'decimal:2',
            'loaded_total_weight_kg' => 'decimal:3',
            'loaded_pieces' => 'decimal:2',
            'loaded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productPackaging(): BelongsTo
    {
        return $this->belongsTo(ProductPackaging::class);
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class);
    }

    public function productGramVariant(): BelongsTo
    {
        return $this->belongsTo(ProductGramVariant::class);
    }

    public function loadedGramVariant(): BelongsTo
    {
        return $this->belongsTo(ProductGramVariant::class, 'loaded_gram_variant_id');
    }

    public function substitutedFromGramVariant(): BelongsTo
    {
        return $this->belongsTo(ProductGramVariant::class, 'substituted_from_gram_variant_id');
    }

    public function isWholeChicken(): bool
    {
        return $this->product_type === ProductType::WholeChicken;
    }

    public function wasSubstitutedDuringLoading(): bool
    {
        return $this->substituted_from_gram_variant_id !== null
            && $this->loaded_gram_variant_id !== $this->substituted_from_gram_variant_id;
    }

    /**
     * Gewicht dat voor een geleverd aantal verpakkingen gefactureerd en op de
     * leverbon gezet wordt. Bij hele kip is het werkelijk ingevulde gewicht
     * leidend (naar verhouding bij een deellevering), daarna het doosgewicht van
     * de geladen variant; anders het bestelde doosgewicht.
     */
    public function billableWeightKg(float $deliveredQuantity): float
    {
        if ($this->isWholeChicken()) {
            if ((float) $this->loaded_actual_weight_kg > 0) {
                $loadedQuantity = (float) ($this->loaded_packaging_quantity ?? $this->quantity);

                return $loadedQuantity > 0
                    ? round((float) $this->loaded_actual_weight_kg * $deliveredQuantity / $loadedQuantity, 3)
                    : 0.0;
            }

            if ($this->loaded_box_weight_kg !== null) {
                return round($deliveredQuantity * (float) $this->loaded_box_weight_kg, 3);
            }
        }

        return round($deliveredQuantity * (float) $this->box_weight_kg, 3);
    }

    /**
     * Eenheid voor factuur en leverbon: bij hele kip de geladen variant als die
     * afwijkt van de bestelde.
     */
    public function billableUnit(): string
    {
        if ($this->isWholeChicken()
            && $this->loaded_gram_variant_id !== null
            && $this->loaded_gram_variant_id !== $this->product_gram_variant_id
            && $this->loadedGramVariant !== null) {
            return sprintf('Doos %s', $this->loadedGramVariant->displayLabel());
        }

        return (string) $this->unit;
    }

    public function effectiveGramVariantLabel(): string
    {
        if ($this->loadedGramVariant) {
            return $this->loadedGramVariant->displayLabel();
        }

        if ($this->productGramVariant) {
            return $this->productGramVariant->displayLabel();
        }

        return $this->packaging_label ?? '';
    }
}
