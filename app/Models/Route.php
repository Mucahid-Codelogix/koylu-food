<?php

namespace App\Models;

use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasFactory;

    protected $guarded = [];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routeStops(){
        return $this->hasMany(RouteStop::class);
    }
}
