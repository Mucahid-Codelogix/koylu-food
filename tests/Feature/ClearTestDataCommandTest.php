<?php

use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\ExactToken;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Queue;

/**
 * Een klant met een volledig doorlopen bestelling: op een route, geleverd,
 * gefactureerd, met kratten, een klantprijs en een inlogaccount.
 *
 * @return array{admin: User, driver: User, vehicle: Vehicle, product: Product, supplier: Supplier, token: ExactToken}
 */
function customerWithFullOrderHistory(array $customerAttributes = []): array
{
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $driver = User::factory()->driver()->create();
    $vehicle = Vehicle::factory()->create();
    $supplier = Supplier::factory()->create();

    $customer = Customer::factory()->create($customerAttributes);
    User::factory()->customer()->create(['customer_id' => $customer->id]);

    $stop = RouteStop::factory()->create([
        'route_id' => Route::factory()->create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id]),
        'order_id' => Order::factory()->placed()->for($customer)->create(),
    ]);
    $order = $stop->order;

    $item = OrderItem::factory()->for($order)->create();
    OrderItem::factory()->for($order)->create([
        'product_id' => $item->product_id,
        'split_from_order_item_id' => $item->id,
    ]);

    $delivery = Delivery::factory()->create(['order_id' => $order->id]);
    DeliveryItem::query()->create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'ordered_quantity' => 1,
        'delivered_quantity' => 1,
    ]);

    Invoice::factory()->create(['order_id' => $order->id]);

    CrateTransaction::create([
        'customer_id' => $customer->id,
        'route_id' => $stop->route_id,
        'crates_given' => 3,
    ]);

    CustomerProductPrice::factory()->create(['customer_id' => $customer->id]);

    $token = ExactToken::storeOrUpdate([
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(10),
        'division' => 459429,
    ]);

    Queue::fake();

    return [
        'admin' => $admin,
        'driver' => $driver,
        'vehicle' => $vehicle,
        'product' => $item->product,
        'supplier' => $supplier,
        'token' => $token,
    ];
}

it('toont in een proefrun wat er gewist wordt zonder iets te wissen', function () {
    customerWithFullOrderHistory();

    $this->artisan('app:clear-test-data', ['--dry-run' => true])
        ->expectsTable(['Onderdeel', 'Te wissen'], [
            ['Facturen', 1],
            ['Routestops', 1],
            ['Routes', 1],
            ['Leverregels', 1],
            ['Leveringen', 1],
            ['Orderregels', 2],
            ['Bestellingen', 1],
            ['Kratten-mutaties', 1],
            ['Klantprijzen', 1],
            ['Inlogaccounts van klanten', 1],
            ['Klanten', 1],
        ])
        ->expectsOutputToContain('Proefrun')
        ->assertSuccessful();

    expect(Customer::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});

it('wist alle klanten met hun bestellingen, routes, leveringen, facturen, kratten, prijzen en logins', function () {
    customerWithFullOrderHistory();

    $this->artisan('app:clear-test-data', ['--force' => true])->assertSuccessful();

    expect(Customer::query()->count())->toBe(0)
        ->and(Order::query()->count())->toBe(0)
        ->and(OrderItem::query()->count())->toBe(0)
        ->and(RouteStop::query()->count())->toBe(0)
        ->and(Route::query()->count())->toBe(0)
        ->and(Delivery::query()->count())->toBe(0)
        ->and(DeliveryItem::query()->count())->toBe(0)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(CrateTransaction::query()->count())->toBe(0)
        ->and(CustomerProductPrice::query()->count())->toBe(0)
        ->and(User::query()->whereNotNull('customer_id')->count())->toBe(0);
});

it('laat artikelen, leveranciers, voertuigen, chauffeurs, beheerders en de Exact-koppeling staan', function () {
    [
        'admin' => $admin,
        'driver' => $driver,
        'vehicle' => $vehicle,
        'product' => $product,
        'supplier' => $supplier,
    ] = customerWithFullOrderHistory();

    $this->artisan('app:clear-test-data', ['--force' => true])->assertSuccessful();

    expect($admin->fresh())->not->toBeNull()
        ->and($driver->fresh())->not->toBeNull()
        ->and($vehicle->fresh())->not->toBeNull()
        ->and($product->fresh())->not->toBeNull()
        ->and($supplier->fresh())->not->toBeNull()
        ->and(ExactToken::query()->count())->toBe(1);
});

it('stuurt niets naar Exact tijdens het wissen', function () {
    customerWithFullOrderHistory();

    $this->artisan('app:clear-test-data', ['--force' => true])->assertSuccessful();

    Queue::assertNothingPushed();
});

it('waarschuwt als klanten een eigen minimum bestelbedrag of kratprijs hebben', function () {
    customerWithFullOrderHistory(['crate_deposit_price' => 2.50]);

    $this->artisan('app:clear-test-data', ['--dry-run' => true])
        ->expectsOutputToContain('1 klanten hebben een eigen minimum bestelbedrag of kratprijs')
        ->assertSuccessful();
});

it('wist niets als de bevestiging wordt geweigerd', function () {
    customerWithFullOrderHistory();

    $this->artisan('app:clear-test-data')
        ->expectsConfirmation('Alle klanten en alles hierboven definitief wissen?', 'no')
        ->expectsOutputToContain('Afgebroken')
        ->assertFailed();

    expect(Customer::query()->count())->toBe(1)
        ->and(Invoice::query()->count())->toBe(1);
});
