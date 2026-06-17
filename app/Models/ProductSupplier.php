<?php

namespace App\Models;

use Database\Factories\ProductSupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSupplier extends Model
{
    /** @use HasFactory<ProductSupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'price_per_kg',
        'supplier_sku',
        'exact_article_code',
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
            'price_per_kg' => 'decimal:4',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function calculatePackagingPrice(ProductPackaging $packaging): string
    {
        return sprintf(
            '%.4f',
            round((float) $this->price_per_kg * (float) $packaging->weight_kg, 4),
        );
    }

    public function calculateLineSubtotal(ProductPackaging $packaging, float|int|string $quantity): string
    {
        $packagingPrice = (float) $this->calculatePackagingPrice($packaging);

        return sprintf('%.2f', round($packagingPrice * (float) $quantity, 2));
    }
}
