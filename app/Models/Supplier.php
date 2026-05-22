<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'vat_number',
        'kvk_number',
        'address',
        'is_active',
    ];

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_suppliers')
            ->withPivot([
                'id',
                'price_per_kg',
                'supplier_sku',
                'exact_article_code',
                'sort_order',
                'is_default',
                'is_active',
            ])
            ->withTimestamps();
    }
}
