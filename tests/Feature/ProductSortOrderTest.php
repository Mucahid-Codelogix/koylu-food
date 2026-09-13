<?php

use App\Enums\ProductType;
use App\Filament\Driver\Pages\DriverLoadingPhase;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\LoadingProductOrder;
use App\Filament\Resources\Products\Pages\ShopProductOrder;
use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncProductToExact;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('toont producten in de shop in de shopvolgorde, los van de laadvolgorde', function () {
    $user = User::factory()->create(['customer_id' => Customer::factory()->create()->id]);

    $tweede = Product::factory()->standard()->create(['name' => 'Aaa kipfilet', 'shop_sort_order' => 2, 'loading_sort_order' => 1]);
    $eerste = Product::factory()->standard()->create(['name' => 'Zzz vleugels', 'shop_sort_order' => 1, 'loading_sort_order' => 2]);

    $component = Livewire::actingAs($user)->test('customer.shop-products');

    expect($component->get('products')->pluck('id')->all())->toBe([$eerste->id, $tweede->id]);
});

it('laadt producten in de laadvolgorde, los van de shopvolgorde en de volgorde van de stops', function () {
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create(['driver_id' => $driver->id]);

    $eerst = Product::factory()->create(['name' => 'Kipfilet', 'loading_sort_order' => 1, 'shop_sort_order' => 2]);
    $later = Product::factory()->create(['name' => 'Kippenvleugels', 'loading_sort_order' => 2, 'shop_sort_order' => 1]);

    // Stop 1 bestelt het product dat later geladen moet worden.
    foreach ([1 => $later, 2 => $eerst] as $stopOrder => $product) {
        $order = Order::factory()->placed()->for(Customer::factory())->create();

        RouteStop::factory()->create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_type' => ProductType::Standard,
            'product_gram_variant_id' => null,
            'unit' => 'Doos 10.000 kg',
            'quantity' => 1,
        ]);
    }

    $this->actingAs($driver);

    $products = Testable::create(DriverLoadingPhase::class, [], ['routeId' => $route->id])->get('products');

    expect(collect($products)->pluck('name')->all())->toBe(['Kipfilet', 'Kippenvleugels']);
});

it('zet een nieuw product achteraan in zowel de shopvolgorde als de laadvolgorde', function () {
    Product::factory()->create(['shop_sort_order' => 5, 'loading_sort_order' => 9]);

    $nieuw = Product::factory()->create();

    expect($nieuw->shop_sort_order)->toBe(6)
        ->and($nieuw->loading_sort_order)->toBe(10);
});

it('slaat op de shopvolgorde-pagina alleen de shopvolgorde op, zonder Exact-sync', function () {
    $this->actingAs(User::factory()->admin()->create());

    $a = Product::factory()->create(['name' => 'Kipfilet', 'shop_sort_order' => 1, 'loading_sort_order' => 1]);
    $b = Product::factory()->create(['name' => 'Hele kip', 'shop_sort_order' => 2, 'loading_sort_order' => 2]);
    $c = Product::factory()->create(['name' => 'Kippenvleugels', 'shop_sort_order' => 3, 'loading_sort_order' => 3]);

    Bus::fake();

    Livewire::test(ShopProductOrder::class)
        ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true)
        ->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

    expect(Product::query()->orderBy('shop_sort_order')->pluck('id')->all())->toBe([$c->id, $a->id, $b->id])
        ->and(Product::query()->orderBy('loading_sort_order')->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);

    Bus::assertNotDispatched(SyncProductToExact::class);
});

it('slaat op de laadvolgorde-pagina alleen de laadvolgorde op, zonder Exact-sync', function () {
    $this->actingAs(User::factory()->admin()->create());

    $a = Product::factory()->create(['name' => 'Kipfilet', 'shop_sort_order' => 1, 'loading_sort_order' => 1]);
    $b = Product::factory()->create(['name' => 'Hele kip', 'shop_sort_order' => 2, 'loading_sort_order' => 2]);

    Bus::fake();

    Livewire::test(LoadingProductOrder::class)
        ->assertCanSeeTableRecords([$a, $b], inOrder: true)
        ->call('reorderTable', [(string) $b->id, (string) $a->id]);

    expect(Product::query()->orderBy('loading_sort_order')->pluck('id')->all())->toBe([$b->id, $a->id])
        ->and(Product::query()->orderBy('shop_sort_order')->pluck('id')->all())->toBe([$a->id, $b->id]);

    Bus::assertNotDispatched(SyncProductToExact::class);
});

it('biedt vanuit de productenlijst knoppen naar beide volgordepagina\'s', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListProducts::class)
        ->assertActionHasUrl('shopOrder', ProductResource::getUrl('shop-order'))
        ->assertActionHasUrl('loadingOrder', ProductResource::getUrl('loading-order'));

    $this->get(ProductResource::getUrl('shop-order'))->assertOk()->assertSee('Shopvolgorde');
    $this->get(ProductResource::getUrl('loading-order'))->assertOk()->assertSee('Laadvolgorde');
});
