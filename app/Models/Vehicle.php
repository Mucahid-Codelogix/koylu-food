<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'brand',
        'license_plate',
        'model',
    ];

    public function routes()
    {
        return $this->hasMany(Route::class);
    }
}
