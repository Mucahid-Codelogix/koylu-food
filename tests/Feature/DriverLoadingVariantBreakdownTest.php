<?php

use App\Enums\ProductType;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;

it('splitst hele kip uit per gramvariant in de laadkaart', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);

    $product = Product::factory()->create(['name' => 'Hele kip']);
    $variant1000 = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1000,
    ]);
    $variant1200 = ProductGramVariant::factory()->create([
        'product_id' => $product->id,
        'weight_grams' => 1200,
    ]);

    $makeItem = function (int $stopOrder, ProductGramVariant $variant, float $boxes, float $pieces, float $kg, string $supplier) use ($route, $product): void {
        $order = Order::factory()->placed()->create();
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
            'supplier_name' => $supplier,
        ]);
    };

    // Twee klanten bestellen 1000 g (van twee leveranciers), één klant bestelt 1200 g.
    $makeItem(1, $variant1000, 2, 20, 20.0, 'Pluimveehandel Noord');
    $makeItem(2, $variant1000, 3, 30, 30.0, 'Kip Express BV');
    $makeItem(3, $variant1200, 1, 10, 12.0, 'Pluimveehandel Noord');

    $this->actingAs($driver);

    $component = Testable::create(
        DriverLoadingPhase::class,
        [],
        ['routeId' => $route->id],
    );

    $products = $component->get('products');

    expect($products)->toHaveCount(1);

    // Leveranciers op productniveau met aantal dozen per leverancier.
    expect($products[0]['supplier_breakdown'])->toBe([
        'Pluimveehandel Noord' => 3.0,
        'Kip Express BV' => 3.0,
    ]);

    $breakdown = collect($products[0]['variant_breakdown']);

    // Beide gramvarianten moeten los zichtbaar zijn, gesorteerd op gewicht.
    expect($breakdown)->toHaveCount(2);
    expect($breakdown->pluck('weight_grams')->all())->toBe([1000, 1200]);

    $v1000 = $breakdown->firstWhere('weight_grams', 1000);
    expect($v1000['boxes'])->toBe(5.0);
    expect($v1000['pieces'])->toBe(50.0);
    expect($v1000['kg'])->toBe(50.0);
    // Aantal dozen per leverancier voor de 1000 g variant.
    expect($v1000['supplier_breakdown'])->toBe([
        'Pluimveehandel Noord' => 2.0,
        'Kip Express BV' => 3.0,
    ]);

    $v1200 = $breakdown->firstWhere('weight_grams', 1200);
    expect($v1200['boxes'])->toBe(1.0);
    expect($v1200['pieces'])->toBe(10.0);
    expect($v1200['kg'])->toBe(12.0);
    expect($v1200['supplier_breakdown'])->toBe(['Pluimveehandel Noord' => 1.0]);
});

it('toont per klantregel de eigen verpakking en splitst een product met meerdere verpakkingen uit', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);

    $product = Product::factory()->create(['name' => 'Hele kip']);

    $makeItem = function (int $stopOrder, string $unit, float $quantity, float $boxWeightKg, string $supplier) use ($route, $product): void {
        $order = Order::factory()->placed()->create();
        RouteStop::factory()->create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Hele kip',
            'product_type' => ProductType::Standard,
            'product_gram_variant_id' => null,
            'unit' => $unit,
            'quantity' => $quantity,
            'box_weight_kg' => $boxWeightKg,
            'ordered_pieces' => null,
            'ordered_total_weight_kg' => $quantity * $boxWeightKg,
            'supplier_name' => $supplier,
        ]);
    };

    // Klant 1 bestelt 2 kratten 1400 g, klant 2 bestelt 1 krat 1200 g.
    $makeItem(1, 'Krat 1400 g', 2, 14.0, 'Pluimveehandel Noord');
    $makeItem(2, 'Krat 1200 g', 1, 12.0, 'Kip Express BV');

    $this->actingAs($driver);

    $component = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);

    $product = $component->get('products')[0];

    // Elke klantregel houdt zijn eigen verpakking.
    expect(collect($product['customers'])->pluck('unit')->all())->toBe(['Krat 1400 g', 'Krat 1200 g']);

    $breakdown = collect($product['packaging_breakdown']);

    expect($breakdown->pluck('label')->all())->toBe(['Krat 1200 g', 'Krat 1400 g']);
    expect($breakdown->firstWhere('label', 'Krat 1400 g')['quantity'])->toBe(2.0);
    expect($breakdown->firstWhere('label', 'Krat 1400 g')['kg'])->toBe(28.0);
    expect($breakdown->firstWhere('label', 'Krat 1200 g')['supplier_breakdown'])->toBe(['Kip Express BV' => 1.0]);

    $component
        ->assertSee('Per verpakking laden')
        ->assertSee('3 verpakkingen')
        ->assertDontSee('3 Krat 1400 g')
        // Aantal, gewicht per verpakking en totaal zijn los leesbaar.
        ->assertSee('2x à 14 kg · 28,00 kg totaal')
        ->assertSee('totaal 28,00 kg');
});
