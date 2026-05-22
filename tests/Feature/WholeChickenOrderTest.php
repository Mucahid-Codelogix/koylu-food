<?php

use App\Enums\ProductType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\OrderItemLoadingService;
use App\Services\OrderItemSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createWholeChickenProduct(
    float $pricePerKg = 6.5,
    int $weightGrams = 750,
    int $piecesPerBox = 12,
    float $boxWeightKg = 10.5,
): Product {
    $product = Product::factory()->create([
        'name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'allows_loading_substitute' => true,
    ]);

    ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => $weightGrams,
        'pieces_per_box' => $piecesPerBox,
        'box_weight_kg' => $boxWeightKg,
        'is_default' => true,
    ]);

    ProductSupplier::factory()->for($product)->for(Supplier::factory()->create())->create([
        'price_per_kg' => $pricePerKg,
        'is_default' => true,
    ]);

    return $product->fresh(['activeGramVariants', 'activeProductSuppliers.supplier']);
}

function createTestOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
        'customer_id' => Customer::factory()->create()->id,
        'status' => 'placed',
        'order_date' => now(),
        'total_price' => 0,
    ]);
}

it('builds a whole chicken order item snapshot with pieces and weight', function () {
    $product = createWholeChickenProduct(pricePerKg: 6.5, boxWeightKg: 10.5);
    $gramVariant = $product->defaultGramVariant();
    $productSupplier = $product->defaultProductSupplier();

    $snapshot = app(OrderItemSnapshotBuilder::class)->fromWholeChickenLine(
        $product,
        $gramVariant,
        $productSupplier,
        2,
    );

    expect($snapshot['product_type'])->toBe(ProductType::WholeChicken)
        ->and($snapshot['product_gram_variant_id'])->toBe($gramVariant->id)
        ->and($snapshot['ordered_pieces'])->toBe(24.0)
        ->and($snapshot['ordered_total_weight_kg'])->toBe(21.0)
        ->and($snapshot['unit_price'])->toBe(68.25)
        ->and($snapshot['subtotal'])->toBe(136.5);
});

it('records a loading substitute with reason on order items', function () {
    $product = createWholeChickenProduct();
    $orderedVariant = $product->defaultGramVariant();
    $alternateVariant = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12.35,
        'is_default' => false,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => createTestOrder()->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $orderedVariant->id,
        'unit' => 'Doos',
        'quantity' => 2,
        'unit_price' => 68.25,
        'subtotal' => 136.5,
        'ordered_pieces' => 24,
        'ordered_total_weight_kg' => 21,
    ]);

    $updated = app(OrderItemLoadingService::class)->recordLoadedVariant(
        $orderItem,
        $alternateVariant,
        'Niet op voorraad',
    );

    expect($updated->loaded_gram_variant_id)->toBe($alternateVariant->id)
        ->and($updated->substituted_from_gram_variant_id)->toBe($orderedVariant->id)
        ->and($updated->loading_substitution_reason)->toBe('Niet op voorraad')
        ->and((float) $updated->loaded_pieces)->toBe(20.0)
        ->and((float) $updated->loaded_total_weight_kg)->toBe(24.7)
        ->and($updated->wasSubstitutedDuringLoading())->toBeTrue()
        ->and($updated->loaded_at)->not->toBeNull();
});

it('initializes loading from the ordered gram variant when not substituted', function () {
    $product = createWholeChickenProduct();
    $variant = $product->defaultGramVariant();

    $orderItem = OrderItem::create([
        'order_id' => createTestOrder()->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $variant->id,
        'unit' => 'Doos',
        'quantity' => 1,
        'unit_price' => 68.25,
        'subtotal' => 68.25,
        'ordered_pieces' => 12,
        'ordered_total_weight_kg' => 10.5,
    ]);

    $updated = app(OrderItemLoadingService::class)->initializeFromOrder($orderItem);

    expect($updated->loaded_gram_variant_id)->toBe($variant->id)
        ->and($updated->substituted_from_gram_variant_id)->toBeNull()
        ->and($updated->loading_substitution_reason)->toBeNull();
});

it('adds whole chicken to cart using price per kg times box weight', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = createWholeChickenProduct(pricePerKg: 6.5, boxWeightKg: 10.5);

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->assertSet('total', 68.25);
});

it('persists whole chicken snapshots when placing an order', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create(['customer_id' => $customer->id]);
    $product = createWholeChickenProduct();

    Livewire::actingAs($user)
        ->test('customer.shop-products')
        ->call('addToCart', $product->id)
        ->call('placeOrder');

    $order = Order::query()->where('customer_id', $customer->id)->first();
    $item = $order->items()->first();

    expect($item)->not->toBeNull()
        ->and($item->product_type)->toBe(ProductType::WholeChicken)
        ->and($item->product_gram_variant_id)->not->toBeNull()
        ->and($item->ordered_pieces)->toBeGreaterThan(0)
        ->and($item->ordered_total_weight_kg)->toBeGreaterThan(0)
        ->and((float) $item->price_per_kg)->toBe(6.5);
});
