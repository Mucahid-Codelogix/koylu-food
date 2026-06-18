<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExactSyncLog extends Model
{
    protected $guarded = [];

    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }
}
