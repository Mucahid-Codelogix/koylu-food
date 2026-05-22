<?php

namespace App\Models;

use Database\Factories\ProductGramVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return sprintf('%d g', $this->weight_grams);
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
