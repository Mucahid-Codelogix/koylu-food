<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Models\Concerns\GuardsDeletion;
use App\Observers\ProductObserver;
use App\Support\UploadStorage;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use GuardsDeletion, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'product_type',
        'allows_loading_substitute',
        'min_order_quantity',
        'image_path',
        'is_active',
        'vat_category',
        'exact_article_code',
        'exact_synced_at',
        'exact_sync_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'allows_loading_substitute' => 'boolean',
            'min_order_quantity' => 'decimal:2',
            'is_active' => 'boolean',
            'vat_category' => VatCategory::class,
            'exact_synced_at' => 'datetime',
        ];
    }

    public function isWholeChicken(): bool
    {
        return $this->product_type === ProductType::WholeChicken;
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return UploadStorage::url($this->image_path);
    }

    public function gramVariants(): HasMany
    {
        return $this->hasMany(ProductGramVariant::class)->orderBy('sort_order');
    }

    public function activeGramVariants(): HasMany
    {
        return $this->gramVariants()->where('is_active', true);
    }

    public function packagings(): HasMany
    {
        return $this->hasMany(ProductPackaging::class)->orderBy('sort_order');
    }

    public function activePackagings(): HasMany
    {
        return $this->packagings()->where('is_active', true);
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class)->orderBy('sort_order');
    }

    public function activeProductSuppliers(): HasMany
    {
        return $this->productSuppliers()->where('is_active', true);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
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

    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function defaultGramVariant(): ?ProductGramVariant
    {
        return $this->activeGramVariants()
            ->where('is_default', true)
            ->first()
            ?? $this->activeGramVariants()->first();
    }

    public function defaultPackaging(): ?ProductPackaging
    {
        return $this->activePackagings()
            ->where('is_default', true)
            ->first()
            ?? $this->activePackagings()->first();
    }

    public function defaultProductSupplier(): ?ProductSupplier
    {
        return $this->activeProductSuppliers()
            ->where('is_default', true)
            ->first()
            ?? $this->activeProductSuppliers()->first();
    }

    public function calculateLineSubtotal(
        ProductPackaging $packaging,
        ProductSupplier $productSupplier,
        float|int|string $quantity,
    ): string {
        return $productSupplier->calculateLineSubtotal($packaging, $quantity);
    }

    public function canBeDeleted(): bool
    {
        return ! $this->orderItems()->exists()
            && ! $this->deliveryItems()->exists();
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->orderItems()->exists()) {
            return 'Dit product is besteld en kan niet worden verwijderd. Deactiveer het product in plaats daarvan.';
        }

        if ($this->deliveryItems()->exists()) {
            return 'Dit product is geleverd en kan niet worden verwijderd. Deactiveer het product in plaats daarvan.';
        }

        return null;
    }
}
