<?php

namespace App\Models;

use Database\Factories\CustomerProductPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProductPrice extends Model
{
    /** @use HasFactory<CustomerProductPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'product_supplier_id',
        'price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSupplier(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class);
    }

    protected static function booted(): void
    {
        static::saving(function (CustomerProductPrice $customerProductPrice): void {
            if ($customerProductPrice->product_supplier_id !== null && $customerProductPrice->product_id === null) {
                $customerProductPrice->product_id = ProductSupplier::query()
                    ->whereKey($customerProductPrice->product_supplier_id)
                    ->value('product_id');
            }
        });
    }
}
