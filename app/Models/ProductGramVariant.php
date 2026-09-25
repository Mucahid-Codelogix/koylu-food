<?php

namespace App\Models;

use Database\Factories\ProductGramVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductGramVariant extends Model
{
    /** @use HasFactory<ProductGramVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'weight_grams',
        'pieces_per_box',
        'box_weight_kg',
        'label',
        'sort_order',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'box_weight_kg' => 'decimal:3',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSuppliers(): BelongsToMany
    {
        return $this->belongsToMany(ProductSupplier::class, 'product_gram_variant_supplier')
            ->withTimestamps();
    }

    /**
     * Zonder koppelingen is de variant bij elke leverancier van het product beschikbaar.
     */
    public function isAvailableForProductSupplier(?int $productSupplierId): bool
    {
        if ($this->productSuppliers->isEmpty()) {
            return true;
        }

        return $productSupplierId !== null
            && $this->productSuppliers->contains('id', $productSupplierId);
    }

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return sprintf('%d g', $this->weight_grams);
    }

    /**
     * Korte weergave voor smalle keuzelijsten: de gramvariant en het doosgewicht,
     * zodat de kilo's niet wegvallen achter het afkappen van de tekst.
     */
    public function shortBoxDescription(): string
    {
        return sprintf(
            '%s · %s kg/doos',
            $this->displayLabel(),
            number_format((float) $this->box_weight_kg, 2, ',', '.')
        );
    }

    public function boxDescription(): string
    {
        return sprintf(
            '%s · %d stuks/doos · %s kg',
            $this->displayLabel(),
            $this->pieces_per_box,
            number_format((float) $this->box_weight_kg, 2, ',', '.')
        );
    }

    public function calculateOrderedPieces(float|int|string $boxQuantity): float
    {
        return round((float) $boxQuantity * $this->pieces_per_box, 2);
    }

    public function calculateTotalWeightKg(float|int|string $boxQuantity): float
    {
        return round((float) $boxQuantity * (float) $this->box_weight_kg, 3);
    }
}
