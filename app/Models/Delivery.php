<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Models\Concerns\GuardsDeletion;
use Database\Factories\DeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    /** @use HasFactory<DeliveryFactory> */
    use GuardsDeletion, HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function canBeDeleted(): bool
    {
        return false;
    }

    public function deletionBlockReason(): ?string
    {
        return 'Leveringen kunnen niet worden verwijderd.';
    }
}
