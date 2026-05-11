<?php

namespace App\Observers;

use App\Enums\RouteStatus;
use App\Models\Route;

class RouteObserver
{

    public function creating(Route $route): void
    {
        $route->status ??= RouteStatus::PLANNED;
    }

    /**
     * Handle the Route "created" event.
     */
    public function created(Route $route): void
    {
        //
    }

    /**
     * Handle the Route "updated" event.
     */
    public function updated(Route $route): void
    {
        //
    }

    /**
     * Handle the Route "deleted" event.
     */
    public function deleted(Route $route): void
    {
        //
    }

    /**
     * Handle the Route "restored" event.
     */
    public function restored(Route $route): void
    {
        //
    }

    /**
     * Handle the Route "force deleted" event.
     */
    public function forceDeleted(Route $route): void
    {
        //
    }
}
