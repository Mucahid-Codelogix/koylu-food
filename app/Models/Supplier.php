<?php

namespace App\Models;

use App\Models\Concerns\GuardsDeletion;
use App\Observers\SupplierObserver;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Query-helper: verpakkingen die expliciet aan deze leverancier gekoppeld zijn (niet generiek).
     *
     * Let op: dit is bewust een query-Builder en GEEN Eloquent-relatie. Gebruik het niet
     * als Filament RelationManager-relationship (dat vereist een echte Relation-instantie).
     *
     * @return Builder<ProductPackaging>
     */
    public function linkedPackagings(): Builder
    {
        return ProductPackaging::query()
            ->whereIn('id', function ($query): void {
                $query->select('product_packaging_supplier.product_packaging_id')
                    ->from('product_packaging_supplier')
                    ->join('product_suppliers', 'product_suppliers.id', '=', 'product_packaging_supplier.product_supplier_id')
                    ->where('product_suppliers.supplier_id', $this->getKey());
            });
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
