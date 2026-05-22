<?php

namespace App\Models;

use App\Services\ProductPricingService;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function pricePerKgForProduct(Product $product, ?ProductSupplier $productSupplier = null): float
    {
        $offer = $productSupplier ?? $product->defaultProductSupplier();

        if ($offer === null) {
            return 0.0;
        }

        return app(ProductPricingService::class)->pricePerKg($this, $offer);
    }
}
