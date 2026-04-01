<?php

namespace App\Models;

use Database\Factories\RouteStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class RouteStop extends Model implements Sortable
{
    /** @use HasFactory<RouteStopFactory> */
    use HasFactory, SortableTrait;

    protected $guarded = [];

    protected $fillable = ['stop_order'];

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
}
