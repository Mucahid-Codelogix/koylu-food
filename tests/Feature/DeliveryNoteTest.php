<?php

use App\Enums\DeliveryStatus;
use App\Enums\ProductType;
use App\Filament\Resources\Deliveries\Pages\ViewDelivery;
use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\DeliveryNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function orderOnRoute(?User $driver = null): array
{
    $driver ??= User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);
    $order = Order::factory()->placed()->for(Customer::factory())->create();

    $product = Product::factory()->create(['name' => 'Kipfilet']);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Kipfilet',
        'unit' => 'Doos 10.000 kg',
        'quantity' => 3,
        'box_weight_kg' => 10,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    return [$driver, $route, $order->fresh()];
}

it('kent een uniek leverbonnummer toe zodra een order wordt ingepland', function () {
    [, , $order] = orderOnRoute();

    expect($order->delivery_note_number)->toStartWith('LB'.now()->year.'-');

    [, , $second] = orderOnRoute();

    expect($second->delivery_note_number)->not->toBe($order->delivery_note_number);
});

it('bouwt een werkbon met lege krat- en handtekeningvakken', function () {
    [, , $order] = orderOnRoute();

    $data = app(DeliveryNoteService::class)->viewData($order);

    expect($data['isFinal'])->toBeFalse()
        ->and($data['signatures'])->toBe(['receiver' => null, 'driver' => null])
        ->and($data['crates'])->toBe(['given' => 0, 'returned' => 0])
        ->and($data['lines'][0]['quantity'])->toBe(3.0)
        ->and($data['lines'][0]['weight_kg'])->toBe(30.0)
        ->and($data['totals']['weight_kg'])->toBe(30.0);
});

it('rekent de werkbon met het geladen aantal in plaats van het bestelde', function () {
    [, , $order] = orderOnRoute();

    $order->items->first()->update(['loaded_packaging_quantity' => 2]);

    $data = app(DeliveryNoteService::class)->viewData($order->fresh('items'));

    expect($data['lines'][0]['quantity'])->toBe(2.0)
        ->and($data['lines'][0]['weight_kg'])->toBe(20.0);
});

it('bouwt een definitieve leverbon met geleverde aantallen en kratten', function () {
    [, $route, $order] = orderOnRoute();
    $orderItem = $order->items->first();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::PARTIAL,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $orderItem->id,
        'product_id' => $orderItem->product_id,
        'ordered_quantity' => 3,
        'delivered_quantity' => 2,
        'return_note' => 'Correctie bij laden: origineel 3, geladen 2',
    ]);

    CrateTransaction::create([
        'customer_id' => $order->customer_id,
        'route_id' => $route->id,
        'crates_given' => 10,
        'crates_returned' => 4,
    ]);

    $data = app(DeliveryNoteService::class)->viewData($order->fresh('items'), $delivery->fresh('items'));

    expect($data['isFinal'])->toBeTrue()
        ->and($data['crates'])->toBe(['given' => 10, 'returned' => 4])
        ->and($data['lines'][0]['delivered_quantity'])->toBe(2.0)
        ->and($data['lines'][0]['has_deviation'])->toBeTrue()
        ->and($data['lines'][0]['deviation'])->toBe(1.0)
        ->and($data['lines'][0]['return_note'])->toContain('origineel 3, geladen 2')
        ->and($data['totals']['weight_kg'])->toBe(20.0);
});

it('geeft de werkbon als pdf aan een admin', function () {
    [, , $order] = orderOnRoute();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('leverbon.concept', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('geeft alle werkbonnen van een route als één pdf aan de eigen chauffeur', function () {
    [$driver, $route] = orderOnRoute();

    $this->actingAs($driver)
        ->get(route('leverbon.route', $route))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('geeft geleverde stops hun definitieve bon in de route-pdf en de rest een werkbon', function () {
    [$driver, $route, $order] = orderOnRoute();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    $secondOrder = Order::factory()->placed()->for(Customer::factory())->create();
    OrderItem::factory()->create([
        'order_id' => $secondOrder->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 1,
        'box_weight_kg' => 5,
    ]);
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $secondOrder->id,
        'stop_order' => 2,
    ]);

    $this->actingAs($driver)
        ->get(route('leverbon.route', $route))
        ->assertOk();

    $service = app(DeliveryNoteService::class);

    expect($service->viewData($order->fresh(), $delivery)['isFinal'])->toBeTrue()
        ->and($service->viewData($secondOrder->fresh())['isFinal'])->toBeFalse();
});

it('weigert de werkbon aan een chauffeur van een andere route', function () {
    [, , $order] = orderOnRoute();

    $this->actingAs(User::factory()->driver()->create())
        ->get(route('leverbon.concept', $order))
        ->assertForbidden();
});

it('weigert de werkbon aan een klant', function () {
    [, , $order] = orderOnRoute();

    $customerUser = User::factory()->customer()->create(['customer_id' => $order->customer_id]);

    $this->actingAs($customerUser)
        ->get(route('leverbon.concept', $order))
        ->assertForbidden();
});

it('geeft de definitieve leverbon aan de eigen klant maar niet aan een andere', function () {
    [, , $order] = orderOnRoute();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    $ownUser = User::factory()->customer()->create(['customer_id' => $order->customer_id]);
    $otherUser = User::factory()->customer()->create(['customer_id' => Customer::factory()->create()->id]);

    $this->actingAs($ownUser)
        ->get(route('leverbon.download', $delivery))
        ->assertOk();

    $this->actingAs($otherUser)
        ->get(route('leverbon.download', $delivery))
        ->assertForbidden();
});

it('opent de definitieve leverbon in de browser om te bekijken en downloadt hem anders', function () {
    [, , $order] = orderOnRoute();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    $admin = User::factory()->admin()->create();

    $inline = $this->actingAs($admin)
        ->get(route('leverbon.download', ['delivery' => $delivery, 'bekijken' => 1]))
        ->assertOk();

    $download = $this->actingAs($admin)
        ->get(route('leverbon.download', $delivery))
        ->assertOk();

    expect($inline->headers->get('content-disposition'))->toStartWith('inline')
        ->and($download->headers->get('content-disposition'))->toStartWith('attachment');
});

it('toont kratten en een knop naar de leverbon op de leveringspagina', function () {
    [, $route, $order] = orderOnRoute();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    CrateTransaction::create([
        'customer_id' => $order->customer_id,
        'route_id' => $route->id,
        'crates_given' => 17,
        'crates_returned' => 9,
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(ViewDelivery::class, ['record' => $delivery->id])
        ->assertSee('Meegegeven kratten')
        ->assertSee('17')
        ->assertSee('Retour ontvangen kratten')
        ->assertSee('9')
        ->assertActionVisible('deliveryNote')
        ->assertActionHasUrl('deliveryNote', route('leverbon.download', ['delivery' => $delivery, 'bekijken' => 1]));
});

it('zet op de definitieve leverbon het werkelijke gewicht en de geladen variant van hele kip', function () {
    [, , $order] = orderOnRoute();

    $product = Product::factory()->create(['name' => 'Hele kip', 'product_type' => ProductType::WholeChicken]);
    $ordered = ProductGramVariant::factory()->create(['product_id' => $product->id, 'weight_grams' => 1200, 'label' => '1200 g', 'box_weight_kg' => 12.35]);
    $loaded = ProductGramVariant::factory()->create(['product_id' => $product->id, 'weight_grams' => 1300, 'label' => '1300 g', 'box_weight_kg' => 13.0]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $ordered->id,
        'unit' => 'Doos 1200 g',
        'quantity' => 2,
        'box_weight_kg' => 12.35,
    ]);

    $item->forceFill([
        'loaded_gram_variant_id' => $loaded->id,
        'loaded_box_weight_kg' => 13.0,
        'loaded_packaging_quantity' => 2,
        'loaded_actual_weight_kg' => 26.4,
    ])->save();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $product->id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    $data = app(DeliveryNoteService::class)->viewData($order->fresh('items'), $delivery->fresh('items'));
    $line = collect($data['lines'])->firstWhere('product_name', 'Hele kip');

    expect($line['weight_kg'])->toBe(26.4)
        ->and($line['unit'])->toBe('Doos 1300 g');
});
