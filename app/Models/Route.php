<?php

namespace App\Models;

use App\Enums\RouteStatus;
use App\Observers\RouteObserver;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([RouteObserver::class])]
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => RouteStatus::class,
            'route_date' => 'date',
            'completed_at' => 'date',
        ];
    }

    public function isLoadingComplete(): bool
    {
        return $this->loading_completed_at !== null;
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function routeStops()
    {
        return $this->hasMany(RouteStop::class);
    }
}
