<?php

namespace App\Models;

use App\Models\Concerns\GuardsDeletion;
use App\Observers\SupplierObserver;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([SupplierObserver::class])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use GuardsDeletion, HasFactory;

    protected $fillable = [
        'name',
        'contact_person',
        'email',
        'phone',
        'vat_number',
        'kvk_number',
        'address',
        'is_active',
        'exact_account_id',
        'exact_synced_at',
        'exact_sync_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'exact_synced_at' => 'datetime',
        ];
    }

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

    public function canBeDeleted(): bool
    {
        return ! $this->productSuppliers()->exists();
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->productSuppliers()->exists()) {
            return 'Deze leverancier is gekoppeld aan producten en kan niet worden verwijderd. Deactiveer de leverancier in plaats daarvan.';
        }

        return null;
    }
}
