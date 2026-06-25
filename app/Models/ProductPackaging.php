<?php

namespace App\Models;

use App\Enums\PackagingType;
use Database\Factories\ProductPackagingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductPackaging extends Model
{
    /** @use HasFactory<ProductPackagingFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'packaging_type',
        'weight_kg',
        'label',
        'min_order_quantity',
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
            'packaging_type' => PackagingType::class,
            'weight_kg' => 'decimal:3',
            'min_order_quantity' => 'decimal:2',
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
        return $this->belongsToMany(ProductSupplier::class, 'product_packaging_supplier')
            ->withTimestamps();
    }

    /**
     * Verpakkingen die beschikbaar zijn voor een gegeven ProductSupplier.
     * Generiek (geen koppelingen) = altijd beschikbaar.
     */
    public function scopeForProductSupplier(Builder $query, ?int $productSupplierId): Builder
    {
        return $query->where(function (Builder $q) use ($productSupplierId): void {
            $q->whereDoesntHave('productSuppliers');
            if ($productSupplierId !== null) {
                $q->orWhereHas(
                    'productSuppliers',
                    fn (Builder $sub) => $sub->whereKey($productSupplierId)
                );
            }
        });
    }

    /** Helper voor in-memory filtering (collections met geladen relatie). */
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

        return sprintf('%s %s kg', $this->packaging_type->getLabel(), $this->weight_kg);
    }
}
