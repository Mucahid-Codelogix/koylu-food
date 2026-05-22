<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
