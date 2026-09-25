<?php

use App\Enums\OrderStatus;
use App\Enums\PackagingType;
use App\Enums\ProductType;
use App\Filament\Customer\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderPlacedMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductPackaging;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderRepeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function repeatTestProduct(float $weightKg = 10, float $pricePerKg = 10): Product
{
    $product = Product::factory()->create(['name' => 'Kipfilet']);

    ProductPackaging::factory()->for($product)->create([
        'packaging_type' => PackagingType::Box,
        'weight_kg' => $weightKg,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => $pricePerKg,
        'is_default' => true,
    ]);

    return $product->fresh(['activePackagings', 'activeProductSuppliers.supplier']);
}

function repeatTestOrder(Customer $customer, Product $product, float $quantity = 2): Order
{
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_packaging_id' => $product->activePackagings->first()->id,
        'product_supplier_id' => $product->activeProductSuppliers->first()->id,
        'product_name' => $product->name,
        'quantity' => $quantity,
        'unit_price' => 1,
        'subtotal' => 1,
    ]);

    return $order->fresh('items');
}

it('copies an order with the current prices and a complete snapshot', function () {
    $customer = Customer::factory()->create();
    $product = repeatTestProduct(weightKg: 10, pricePerKg: 10);
    $order = repeatTestOrder($customer, $product, quantity: 2);

    $product->activeProductSuppliers->first()->update(['price_per_kg' => 12]);

    $result = app(OrderRepeatService::class)->placeCopy($order->fresh('items'));
    $newOrder = $result['order'];
    $line = $newOrder->items->first();

    expect($newOrder->id)->not->toBe($order->id)
        ->and($newOrder->order_number)->not->toBe($order->order_number)
        ->and($newOrder->status)->toBe(OrderStatus::PLACED)
        ->and($newOrder->delivery_date)->not->toBeNull()
        ->and((float) $newOrder->total_price)->toBe(240.0)
        ->and($line->product_packaging_id)->toBe($product->activePackagings->first()->id)
        ->and($line->product_supplier_id)->toBe($product->activeProductSuppliers->first()->id)
        ->and((float) $line->unit_price)->toBe(120.0)
        ->and((float) $line->subtotal)->toBe(240.0)
        ->and($line->vat_rate)->not->toBeNull();
});

it('copies the gram variant of a whole chicken line', function () {
    $customer = Customer::factory()->create();

    $product = Product::factory()->create([
        'name' => 'Hele Kip',
        'product_type' => ProductType::WholeChicken,
    ]);
    $gramVariant = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'is_default' => true,
    ]);
    $productSupplier = ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => 6.5,
        'is_default' => true,
    ]);

    $order = Order::factory()->create(['customer_id' => $customer->id]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_gram_variant_id' => $gramVariant->id,
        'product_supplier_id' => $productSupplier->id,
        'product_name' => $product->name,
        'quantity' => 2,
    ]);

    $newOrder = app(OrderRepeatService::class)->placeCopy($order->fresh('items'))['order'];
    $line = $newOrder->items->first();

    expect($line->product_gram_variant_id)->toBe($gramVariant->id)
        ->and($line->product_type)->toBe(ProductType::WholeChicken)
        ->and((float) $line->subtotal)->toBe(156.0);
});

it('skips unavailable lines and reports them', function () {
    $customer = Customer::factory()->create();
    $available = repeatTestProduct(weightKg: 10, pricePerKg: 10);
    $unavailable = repeatTestProduct(weightKg: 5, pricePerKg: 10);
    $unavailable->update(['name' => 'Kippenpoten', 'is_active' => false]);

    $order = repeatTestOrder($customer, $available, quantity: 1);
    OrderItem::factory()->for($order)->create([
        'product_id' => $unavailable->id,
        'product_packaging_id' => $unavailable->activePackagings->first()->id,
        'product_supplier_id' => $unavailable->activeProductSuppliers->first()->id,
        'product_name' => 'Kippenpoten',
        'quantity' => 1,
    ]);

    $result = app(OrderRepeatService::class)->placeCopy($order->fresh('items'));

    expect($result['order']->items)->toHaveCount(1)
        ->and($result['skipped'])->toBe(['Kippenpoten']);
});

it('creates no order when nothing is available anymore', function () {
    $customer = Customer::factory()->create();
    $product = repeatTestProduct();
    $order = repeatTestOrder($customer, $product);
    $product->update(['is_active' => false]);

    $result = app(OrderRepeatService::class)->placeCopy($order->fresh('items'));

    expect($result['order'])->toBeNull()
        ->and(Order::query()->count())->toBe(1);
});

it('places a new order from the order again button', function () {
    Mail::fake();

    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = repeatTestProduct(weightKg: 10, pricePerKg: 10);
    $order = repeatTestOrder($customer, $product, quantity: 2);

    Livewire::actingAs($user)
        ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('orderAgain')
        ->assertHasNoActionErrors();

    expect(Order::query()->count())->toBe(2);

    Mail::assertSent(OrderPlacedMail::class);
});
