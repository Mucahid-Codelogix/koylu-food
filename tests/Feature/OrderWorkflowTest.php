<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\RouteStop;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a placed order as routed', function () {
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    app(OrderWorkflowService::class)->markAsRouted($order);

    expect($order->fresh()->status)->toBe(OrderStatus::ROUTED);
});

it('prevents routing an order that is already on a route', function () {
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    RouteStop::factory()->create(['order_id' => $order->id]);

    app(OrderWorkflowService::class)->assertCanBeRouted($order->fresh());
})->throws(DomainException::class);

it('reverts routed order to placed when removed from route', function () {
    $order = Order::factory()->routed()->for(Customer::factory())->create();

    app(OrderWorkflowService::class)->revertToPlacedIfNotOnRoute($order);

    expect($order->fresh()->status)->toBe(OrderStatus::PLACED);
});
