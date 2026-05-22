<?php

namespace App\Models;

use App\Enums\RouteStopStatus;
use App\Observers\RouteStopObserver;
use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

#[ObservedBy([RouteStopObserver::class])]
class RouteStop extends Model implements Sortable
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory, SortableTrait;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RouteStopStatus::class,
        ];
    }

    public $sortable = [
        'order_column_name' => 'stop_order',
        'sort_when_creating' => true,
    ];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buildSortQuery(): Builder
    {
        return static::query()->where('route_id', $this->route_id);
    }
}
