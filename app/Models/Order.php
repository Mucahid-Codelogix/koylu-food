<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\GuardsDeletion;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use GuardsDeletion, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_date' => 'date',
            'delivery_date' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function scopePlaced(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PLACED);
    }

    public function scopeNotOnRoute(Builder $query): Builder
    {
        return $query->whereDoesntHave('routeStops');
    }

    public function canBeDeleted(): bool
    {
        return false;
    }

    public function deletionBlockReason(): ?string
    {
        return 'Bestellingen kunnen niet worden verwijderd. Gebruik statuswijzigingen indien nodig.';
    }
}
