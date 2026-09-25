<?php

use App\Enums\ProductType;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

it('toont gramvariant, stuks en kilo per klant op de laadkaart voor hele kip', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);

    $product = Product::factory()->create(['name' => 'Hele kip']);
    $variant800 = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 800,
        'label' => '800 g',
    ]);
    $variant1500 = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1500,
        'label' => '1500 g',
    ]);

    $makeItem = function (int $stopOrder, ProductGramVariant $variant, float $boxes, float $pieces, float $kg) use ($route, $product): void {
        $order = Order::factory()->placed()->for(Customer::factory())->create();
        RouteStop::factory()->create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Hele kip',
            'product_type' => ProductType::WholeChicken,
            'product_gram_variant_id' => $variant->id,
            'unit' => 'Doos '.$variant->weight_grams.' g',
            'quantity' => $boxes,
            'ordered_pieces' => $pieces,
            'ordered_total_weight_kg' => $kg,
            'supplier_name' => 'Pluimveehandel Noord',
        ]);
    };

    $makeItem(1, $variant800, 1, 12, 10.8);
    $makeItem(2, $variant1500, 2, 16, 25.0);

    $this->actingAs($driver);

    $component = Testable::create(
        DriverLoadingPhase::class,
        [],
        ['routeId' => $route->id],
    );

    // De variantlabels van beide klanten moeten in de laadkaart staan.
    $component->assertSee('800 g');
    $component->assertSee('1500 g');

    // Per klant het aantal dozen, de stuks en het gewicht.
    $component->assertSee('12 st');
    $component->assertSee('10,80 kg');
    $component->assertSee('16 st');
    $component->assertSee('25,00 kg');

    // Elke klantregel draagt zijn eigen variantlabel mee naar de weergave.
    $customers = collect($component->get('products')[0]['customers']);

    expect($customers->pluck('ordered_variant_label')->all())->toBe(['800 g', '1500 g'])
        ->and($customers->pluck('quantity')->all())->toBe([1.0, 2.0]);
});

it('houdt de laadkaart kleurloos: kleur betekent alleen goed of fout', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);

    $product = Product::factory()->create(['name' => 'Hele kip']);
    $variant = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1200,
        'label' => '1200 g',
    ]);

    $order = Order::factory()->placed()->for(Customer::factory())->create();
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $variant->id,
        'unit' => 'Doos 1200 g',
        'quantity' => 2,
        'ordered_pieces' => 20,
        'ordered_total_weight_kg' => 24,
    ]);

    $this->actingAs($driver);

    $component = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);

    $component->assertSee('1200 g');

    $component->assertDontSeeHtml('bg-sky-500');
    $component->assertDontSeeHtml('bg-violet-500');
    $component->assertDontSeeHtml('bg-fuchsia-500');
});
