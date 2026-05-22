<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;

class OrderWorkflowService
{
    public function assertCanBeRouted(Order $order): void
    {
        if ($order->status !== OrderStatus::PLACED) {
            throw new DomainException(
                "Bestelling {$order->order_number} kan niet ingepland worden (status: {$order->status->value})."
            );
        }

        if ($order->routeStops()->exists()) {
            throw new DomainException(
                "Bestelling {$order->order_number} staat al op een route."
            );
        }
    }

    public function markAsRouted(Order $order): Order
    {
        if ($order->status !== OrderStatus::PLACED) {
            throw new DomainException(
                "Bestelling {$order->order_number} kan niet op ingepland worden gezet (status: {$order->status->value})."
            );
        }

        $order->update(['status' => OrderStatus::ROUTED]);

        return $order->fresh();
    }

    public function markAsDelivered(Order $order): Order
    {
        if (! in_array($order->status, [OrderStatus::ROUTED, OrderStatus::PLACED], true)) {
            throw new DomainException(
                "Bestelling {$order->order_number} kan niet als geleverd worden gemarkeerd."
            );
        }

        $order->update(['status' => OrderStatus::DELIVERED]);

        return $order->fresh();
    }

    public function revertToPlacedIfNotOnRoute(Order $order): Order
    {
        if ($order->routeStops()->exists()) {
            return $order;
        }

        if ($order->status === OrderStatus::ROUTED) {
            $order->update(['status' => OrderStatus::PLACED]);
        }

        return $order->fresh();
    }
}
