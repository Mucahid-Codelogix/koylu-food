<?php

use App\Enums\DeliveryStatus;
use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Filament\Driver\Pages\DriverDashboard;
use App\Filament\Driver\Pages\DriverLoadingPhase;
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
use App\Services\InvoiceLineCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Route, 2: OrderItem, 3: ProductGramVariant, 4: ProductGramVariant}
 */
function boxBreakdownRoute(float $orderedBoxes = 2): array
{
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
    ]);

    $product = Product::factory()->create([
        'name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'allows_loading_substitute' => true,
    ]);

    $ordered = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1200,
        'label' => '1200 g',
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
    ]);

    $alternative = ProductGramVariant::factory()->for($product)->create([
        'weight_grams' => 1300,
        'label' => '1300 g',
        'pieces_per_box' => 10,
        'box_weight_kg' => 13,
    ]);

    $order = Order::factory()->placed()->for(Customer::factory())->create();

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    $item = OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $ordered->id,
        'unit' => 'Doos 1200 g',
        'quantity' => $orderedBoxes,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'price_per_kg' => 6.5,
        'unit_price' => 78,
        'subtotal' => 78 * $orderedBoxes,
        'ordered_pieces' => 10 * $orderedBoxes,
        'ordered_total_weight_kg' => 12 * $orderedBoxes,
    ]);

    return [$driver, $route, $item, $ordered, $alternative];
}

function loadingPage(Route $route): Testable
{
    return Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id]);
}

/**
 * De chauffeur past velden aan in de al klaarstaande verdeling; Alpine zet
 * daarbij de "aangeraakt"-vlag, dus die zetten we hier ook.
 *
 * @param  array<string, mixed>  $values
 */
function editBoxRow(Testable $page, int $itemId, int $index, array $values): Testable
{
    foreach ($values as $key => $value) {
        $page->set("boxBreakdowns.{$itemId}.{$index}.{$key}", $value);
    }

    return $page->set("breakdownTouched.{$itemId}", true);
}

it('zet één veld per bestelde doos klaar, plus lege reserveregels', function () {
    [$driver, $route, $item, $ordered] = boxBreakdownRoute(orderedBoxes: 3);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $page = loadingPage($route)
        ->assertCount("boxBreakdowns.{$item->id}", 3 + DriverLoadingPhase::SPARE_BREAKDOWN_ROWS)
        ->assertSet("boxBreakdowns.{$item->id}.0.variant_id", $ordered->id)
        ->assertSet("boxBreakdowns.{$item->id}.0.quantity", '1')
        ->assertSet("boxBreakdowns.{$item->id}.3.quantity", '0')
        ->assertSet("breakdownTouched.{$item->id}", false)
        ->assertSee('van 3 dozen toegewezen');

    expect($page->get('boxBreakdowns')[$item->id][2]['quantity'])->toBe('1');
});

it('splitst de regel als één van de twee dozen een andere variant krijgt', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id);

    $item->refresh();
    $split = OrderItem::query()->where('split_from_order_item_id', $item->id)->sole();

    expect((float) $item->quantity)->toBe(1.0)
        ->and($item->loaded_gram_variant_id)->toBe($ordered->id)
        ->and($item->billableWeightKg(1))->toBe(12.0)
        ->and((float) $split->quantity)->toBe(1.0)
        ->and($split->loaded_gram_variant_id)->toBe($alternative->id)
        ->and($split->substituted_from_gram_variant_id)->toBe($ordered->id)
        ->and($split->loading_substitution_reason)->toBe('Niet op voorraad')
        ->and($split->billableWeightKg(1))->toBe(13.0)
        ->and($split->product_gram_variant_id)->toBe($ordered->id)
        ->and((float) $split->price_per_kg)->toBe(6.5);
});

it('voegt dozen met dezelfde variant samen tot één regel', function () {
    [$driver, $route, $item, $ordered] = boxBreakdownRoute(orderedBoxes: 3);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    $item->refresh();

    expect(OrderItem::query()->where('split_from_order_item_id', $item->id)->count())->toBe(0)
        ->and((float) $item->quantity)->toBe(3.0)
        ->and($item->loaded_gram_variant_id)->toBe($ordered->id)
        ->and($item->billableWeightKg(3))->toBe(36.0);
});

it('factureert het per doos gewogen gewicht', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("boxBreakdowns.{$item->id}.1.weight_kg", '13.1')
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Andere variant geladen')
        ->call('saveBoxBreakdown', $item->id);

    $split = OrderItem::query()->where('split_from_order_item_id', $item->id)->sole();

    expect($split->billableWeightKg(1))->toBe(13.1)
        ->and($item->fresh()->billableWeightKg(1))->toBe(12.0);
});

it('negeert een gewicht dat maar bij een deel van de dozen is ingevuld', function () {
    [$driver, $route, $item] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.0.weight_kg", '12.4')
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    $item->refresh();

    expect($item->loaded_actual_weight_kg)->toBeNull()
        ->and($item->billableWeightKg(2))->toBe(24.0);
});

it('houdt het bestelde aantal intact als de chauffeur minder dozen laadt', function () {
    [$driver, $route, $item, $ordered] = boxBreakdownRoute(orderedBoxes: 3);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.2.quantity", '0')
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    $item->refresh();

    expect((float) $item->quantity)->toBe(3.0)
        ->and((float) $item->loaded_packaging_quantity)->toBe(2.0)
        ->and($item->billableWeightKg(2))->toBe(24.0);
});

it('laat de chauffeur niet opslaan zonder reden bij een andere variant', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    expect(OrderItem::query()->where('split_from_order_item_id', $item->id)->count())->toBe(0)
        ->and($item->fresh()->loaded_gram_variant_id)->toBeNull();
});

it('slaat een openstaande verdeling ook op bij klaar met laden', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('finishLoading');

    expect(OrderItem::query()->where('split_from_order_item_id', $item->id)->count())->toBe(1)
        ->and($route->fresh()->loading_completed_at)->not->toBeNull();
});

it('toont bij opnieuw wijzigen de verdeling zoals die nu geladen is', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id)
        ->assertCount("boxBreakdowns.{$item->id}", 2 + DriverLoadingPhase::SPARE_BREAKDOWN_ROWS)
        ->assertSet("boxBreakdowns.{$item->id}.0.variant_id", $ordered->id)
        ->assertSet("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->assertSet("breakdownTouched.{$item->id}", false)
        ->assertSee('van 2 dozen toegewezen');
});

it('ruimt de splitsing op als alle dozen weer de bestelde variant krijgen', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $ordered->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    expect(OrderItem::query()->where('split_from_order_item_id', $item->id)->count())->toBe(0)
        ->and((float) $item->fresh()->quantity)->toBe(2.0)
        ->and($item->fresh()->loaded_gram_variant_id)->toBe($ordered->id)
        ->and($item->fresh()->billableWeightKg(2))->toBe(24.0);
});

it('factureert na een splitsing elke doos tegen het juiste gewicht', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("boxBreakdowns.{$item->id}.1.weight_kg", '13.1')
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id);

    $order = $item->fresh()->order;
    $split = OrderItem::query()->where('split_from_order_item_id', $item->id)->sole();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    foreach ([$item->fresh(), $split] as $line) {
        DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $line->id,
            'product_id' => $line->product_id,
            'ordered_quantity' => $line->quantity,
            'delivered_quantity' => $line->quantity,
        ]);
    }

    $lines = app(InvoiceLineCalculator::class)
        ->lines($order->fresh('items'), $delivery->fresh('items'))
        ->keyBy('order_item_id');

    // 12 kg x 6,50 voor de bestelde variant, 13,1 kg x 6,50 voor de gewogen doos.
    expect($lines[$item->id]['delivered_kg'])->toBe(12.0)
        ->and($lines[$item->id]['line_subtotal'])->toBe(78.0)
        ->and($lines[$split->id]['delivered_kg'])->toBe(13.1)
        ->and($lines[$split->id]['line_subtotal'])->toBe(85.15)
        ->and($lines[$split->id]['unit'])->toBe('Doos 1300 g');
});

it('toont de klantenlijst altijd en houdt de hoofdknop bereikbaar', function () {
    [$driver, $route, $item] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->assertSee('Per klant')
        ->assertSee('0/1 geladen')
        ->assertSee('Wijzigen')
        ->assertDontSeeHtml('hidden lg:block')
        ->assertSeeHtml('fixed inset-x-0 bottom-0')
        ->assertSee('Klaar');
});

it('vinkt een klantregel af als geladen, inclusief de afgesplitste dozen', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $page = loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id)
        ->call('toggleRowChecked', $item->id)
        ->assertSee('1/1 geladen');

    $split = OrderItem::query()->where('split_from_order_item_id', $item->id)->sole();

    expect($item->fresh()->loading_checked_at)->not->toBeNull()
        ->and($split->loading_checked_at)->not->toBeNull();

    $page->call('toggleRowChecked', $item->id)->assertSee('0/1 geladen');

    expect($item->fresh()->loading_checked_at)->toBeNull()
        ->and($split->fresh()->loading_checked_at)->toBeNull();
});

it('regelt plus, min en de teller in de browser zonder serververzoek', function () {
    [$driver, $route, $item] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->assertSeeHtml('data-box-qty')
        ->assertSeeHtml('step(')
        ->assertSeeHtml('addRow()')
        ->assertSeeHtml('removeRow(')
        ->assertSeeHtml("breakdownTouched.{$item->id}");
});

it('toont geen paginakop zodat het laadscherm alle hoogte gebruikt', function () {
    [$driver, $route] = boxBreakdownRoute();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    expect(app(DriverLoadingPhase::class)->getHeading())->toBeNull();

    loadingPage($route)->assertDontSeeHtml('fi-header-heading');
});

it('vinkt alleen de aangeklikte regel af, ook bij meerdere klanten', function () {
    [$driver, $route, $item, $ordered] = boxBreakdownRoute(orderedBoxes: 2);

    $tweedeOrder = Order::factory()->placed()->for(Customer::factory())->create();
    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $tweedeOrder->id,
        'stop_order' => 2,
    ]);

    $tweedeItem = OrderItem::factory()->for($tweedeOrder)->create([
        'product_id' => $item->product_id,
        'product_name' => 'Hele kip',
        'product_type' => ProductType::WholeChicken,
        'product_gram_variant_id' => $ordered->id,
        'unit' => 'Doos 1200 g',
        'quantity' => 1,
        'pieces_per_box' => 10,
        'box_weight_kg' => 12,
        'ordered_pieces' => 10,
        'ordered_total_weight_kg' => 12,
    ]);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $page = loadingPage($route)
        ->call('toggleRowChecked', $tweedeItem->id)
        ->assertSee('1/2 geladen');

    expect($tweedeItem->fresh()->loading_checked_at)->not->toBeNull()
        ->and($item->fresh()->loading_checked_at)->toBeNull();

    $checked = collect($page->get('products')[0]['customers'])
        ->filter(fn (array $row): bool => $row['is_checked'])
        ->pluck('order_item_id')
        ->all();

    expect($checked)->toBe([$tweedeItem->id]);
});

it('zet de leverbonknop en de productenlijst in de productbalk', function () {
    [$driver, $route] = boxBreakdownRoute();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->assertSeeHtml('menu: false')
        ->assertSeeHtml('x-show="menu"')
        ->assertSeeHtml(route('leverbon.route', $route))
        ->assertSeeHtml('Leverbonnen downloaden')
        ->assertSee('Alle producten');
});

it('laat de chauffeur na het afronden opnieuw het laadscherm in', function () {
    [$driver, $route, $item] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)->call('finishLoading');

    expect($route->fresh()->loading_completed_at)->not->toBeNull();

    Testable::create(DriverDashboard::class)
        ->assertSee('Terug naar laden')
        ->call('continueLoading')
        ->assertRedirect(DriverLoadingPhase::getUrl().'?routeId='.$route->id);

    // En het scherm zelf blijft bereikbaar met de bestaande verdeling.
    loadingPage($route->fresh())->assertSee('Per klant');
});

it('rekent na het opslaan van 1, 1 en 0 met twee geladen dozen van de drie bestelde', function () {
    [$driver, $route, $item, $ordered] = boxBreakdownRoute(orderedBoxes: 3);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $page = loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.0.quantity", '1')
        ->set("boxBreakdowns.{$item->id}.1.quantity", '1')
        ->set("boxBreakdowns.{$item->id}.2.quantity", '0')
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id);

    $item->refresh();

    expect((float) $item->quantity)->toBe(3.0)
        ->and((float) $item->loaded_packaging_quantity)->toBe(2.0)
        ->and($item->billableWeightKg(2))->toBe(24.0)
        ->and($page->instance()->assignedBoxes($item->id))->toBe(2.0);

    // Na het opnieuw opbouwen staan er nog maar twee dozen klaar.
    $rows = collect($page->get('boxBreakdowns')[$item->id])
        ->filter(fn (array $boxRow): bool => (float) $boxRow['quantity'] > 0);

    expect($rows)->toHaveCount(2);
});

it('toont de teller met de werkelijk toegewezen dozen, ook zonder javascript', function () {
    [$driver, $route, $item] = boxBreakdownRoute(orderedBoxes: 3);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.2.quantity", '0')
        ->set("breakdownTouched.{$item->id}", true)
        ->call('saveBoxBreakdown', $item->id)
        ->assertSeeHtml('assigned: 2')
        ->assertSee('van 3 dozen toegewezen');
});

it('laat bij per gramvariant laden zien welke variant oorspronkelijk besteld was', function () {
    [$driver, $route, $item, $ordered, $alternative] = boxBreakdownRoute(orderedBoxes: 2);

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $page = loadingPage($route)
        ->set("boxBreakdowns.{$item->id}.1.variant_id", $alternative->id)
        ->set("breakdownTouched.{$item->id}", true)
        ->set('loadingData.'.$item->id.'.substitution_reason', 'Niet op voorraad')
        ->call('saveBoxBreakdown', $item->id)
        ->assertSee('i.p.v. 1200 g');

    $breakdown = collect($page->get('products')[0]['variant_breakdown'])
        ->keyBy('label');

    expect($breakdown['1300 g']['substituted_from'])->toBe(['1200 g' => 1.0])
        ->and($breakdown['1200 g']['substituted_from'])->toBe([]);
});
