<?php

namespace App\Models;

use App\Models\Concerns\GuardsDeletion;
use App\Observers\CustomerObserver;
use App\Services\ProductPricingService;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CustomerObserver::class])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use GuardsDeletion, HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_vat_exempt' => 'boolean',
            'is_active' => 'boolean',
            'exact_synced_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function crateTransactions(): HasMany
    {
        return $this->hasMany(CrateTransaction::class);
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

    public function canBeDeleted(): bool
    {
        return ! $this->orders()->exists()
            && ! $this->users()->exists()
            && ! $this->crateTransactions()->exists();
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->orders()->exists()) {
            return 'Deze klant heeft bestellingen en kan niet worden verwijderd. Deactiveer de klant in plaats daarvan.';
        }

        if ($this->users()->exists()) {
            return 'Deze klant heeft gekoppelde gebruikers en kan niet worden verwijderd. Deactiveer de klant in plaats daarvan.';
        }

        if ($this->crateTransactions()->exists()) {
            return 'Deze klant heeft krat-transacties en kan niet worden verwijderd. Deactiveer de klant in plaats daarvan.';
        }

        return null;
    }
}
