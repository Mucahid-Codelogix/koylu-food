<?php

namespace App\Observers;

use App\Models\RouteStop;
use App\Services\OrderWorkflowService;

class RouteStopObserver
{
    public function __construct(
        protected OrderWorkflowService $orders,
    ) {}

    public function creating(RouteStop $routeStop): void
    {
        $order = $routeStop->order()->first();

        if ($order !== null) {
            $this->orders->assertCanBeRouted($order);
        }
    }

    public function created(RouteStop $routeStop): void
    {
        $order = $routeStop->order;

        if ($order !== null) {
            $this->orders->markAsRouted($order);
        }
    }

    public function deleted(RouteStop $routeStop): void
    {
        $order = $routeStop->order;

        if ($order !== null) {
            $this->orders->revertToPlacedIfNotOnRoute($order);
        }
    }
}
