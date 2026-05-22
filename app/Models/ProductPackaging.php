<?php

namespace App\Models;

use App\Enums\PackagingType;
use Database\Factories\ProductPackagingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function displayLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        return sprintf('%s %s kg', $this->packaging_type->getLabel(), $this->weight_kg);
    }
}
