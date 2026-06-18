<?php

use App\Exceptions\RecordNotDeletableException;
use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows deleting a customer without related records', function () {
    $customer = Customer::factory()->create();

    expect($customer->canBeDeleted())->toBeTrue();

    $customer->delete();

    expect(Customer::query()->find($customer->id))->toBeNull();
});

it('blocks deleting a customer with orders', function () {
    $customer = Customer::factory()->create();
    Order::factory()->for($customer)->create();

    expect($customer->canBeDeleted())->toBeFalse();

    expect(fn () => $customer->delete())
        ->toThrow(RecordNotDeletableException::class, 'bestellingen');
});

it('blocks deleting a customer with users', function () {
    $customer = Customer::factory()->create();
    User::factory()->create(['customer_id' => $customer->id]);

    expect($customer->canBeDeleted())->toBeFalse();

    expect(fn () => $customer->delete())
        ->toThrow(RecordNotDeletableException::class, 'gebruikers');
});

it('blocks deleting a customer with crate transactions', function () {
    $customer = Customer::factory()->create();
    CrateTransaction::factory()->create(['customer_id' => $customer->id]);

    expect($customer->canBeDeleted())->toBeFalse();

    expect(fn () => $customer->delete())
        ->toThrow(RecordNotDeletableException::class, 'krat-transacties');
});

it('blocks deleting a customer with orders at database level', function () {
    $customer = Customer::factory()->create();
    Order::factory()->for($customer)->create();

    expect(fn () => $customer->delete())
        ->toThrow(RecordNotDeletableException::class);
});

it('allows deleting a product without order history', function () {
    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create());

    expect($product->canBeDeleted())->toBeTrue();

    $product->delete();

    expect(Product::query()->find($product->id))->toBeNull();
});

it('blocks deleting a product that was ordered', function () {
    $product = Product::withoutEvents(fn () => Product::factory()->standard()->create());
    OrderItem::factory()->create(['product_id' => $product->id]);

    expect($product->canBeDeleted())->toBeFalse();

    expect(fn () => $product->delete())
        ->toThrow(RecordNotDeletableException::class, 'besteld');
});

it('blocks deleting orders', function () {
    $order = Order::factory()->create();

    expect($order->canBeDeleted())->toBeFalse();

    expect(fn () => $order->delete())
        ->toThrow(RecordNotDeletableException::class, 'Bestellingen');
});

it('blocks deleting invoices', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->canBeDeleted())->toBeFalse();

    expect(fn () => $invoice->delete())
        ->toThrow(RecordNotDeletableException::class, 'Facturen');
});

it('blocks deleting deliveries', function () {
    $delivery = Delivery::factory()->create();

    expect($delivery->canBeDeleted())->toBeFalse();

    expect(fn () => $delivery->delete())
        ->toThrow(RecordNotDeletableException::class, 'Leveringen');
});

it('blocks deleting an order that has a delivery', function () {
    $order = Order::factory()->create();
    Delivery::factory()->create(['order_id' => $order->id]);

    expect(fn () => $order->delete())
        ->toThrow(RecordNotDeletableException::class);
});
