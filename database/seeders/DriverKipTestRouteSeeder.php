<?php

namespace Database\Seeders;

use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductSupplier;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;

/**
 * Test-route voor het laadproces (driver@koylu.test), vandaag.
 *
 * Doel: de chauffeur-laadweergave doortesten met (1) hele kip in meerdere
 * gramvarianten en (2) producten van verschillende leveranciers.
 *
 * Idempotent: verwijdert eerst eigen eerdere testdata (order_number KIPTEST-*).
 * Raakt geen bestaande product-/klantdata aan.
 *
 * Draaien:  php artisan db:seed --class=DriverKipTestRouteSeeder
 */
class DriverKipTestRouteSeeder extends Seeder
{
    private const ORDER_PREFIX = 'KIPTEST';

    public function run(): void
    {
        $this->cleanupPreviousTestData();

        $builder = app(DemoOrderBuilder::class);

        $driver = User::query()->where('email', 'driver@koylu.test')->firstOrFail();
        $vehicle = Vehicle::query()->firstOrFail();
        $customers = Customer::query()->orderBy('id')->take(3)->get();

        $heleKip = Product::query()
            ->where('name', 'Hele Kip')
            ->with('gramVariants')
            ->firstOrFail();

        $variant = fn (int $grams): ProductGramVariant => $heleKip->gramVariants
            ->firstWhere('weight_grams', $grams);

        $kipfilet = Product::query()->where('name', 'Kipfilet')->firstOrFail();
        $kippenvleugels = Product::query()->where('name', 'Kippenvleugels')->firstOrFail();

        // Standaard leverancier van hele kip + een tweede (test) leverancier,
        // zodat gramvarianten zichtbaar van verschillende leveranciers kunnen komen.
        $kipSupplierDefault = $heleKip->defaultProductSupplier();
        $kipSupplierAlt = $this->ensureAlternateChickenSupplier($heleKip);

        // Niet-standaard leveranciers, zodat de productgroepen zichtbaar van
        // elkaar verschillen in leverancier.
        $kipExpress = ProductSupplier::query()
            ->where('product_id', $kipfilet->id)
            ->whereHas('supplier', fn ($q) => $q->where('name', 'Kip Express BV'))
            ->first();

        $albertHeijn = ProductSupplier::query()
            ->where('product_id', $kippenvleugels->id)
            ->whereHas('supplier', fn ($q) => $q->where('name', 'Albert Heijn'))
            ->first();

        $route = Route::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_date' => today(),
            'status' => RouteStatus::PLANNED,
        ]);

        // Stop 1 — hele kip 1000 g (2 doos) + 1200 g (1 doos) via standaard leverancier, Kipfilet via Kip Express BV.
        $stop1 = $this->createOrder($builder, $customers->get(0));
        $builder->addWholeChickenLine($stop1, $heleKip, $customers->get(0), 2, $variant(1000), $kipSupplierDefault);
        $builder->addWholeChickenLine($stop1, $heleKip, $customers->get(0), 1, $variant(1200), $kipSupplierDefault);
        $builder->addStandardLine($stop1, $kipfilet, $customers->get(0), 1, null, $kipExpress);
        $builder->recalculateTotal($stop1);
        $this->attachStop($route, $stop1, 1);

        // Stop 2 — hele kip 1000 g (3 doos, standaard) + 1500 g (1 doos, tweede leverancier), Kippenvleugels via Albert Heijn.
        $stop2 = $this->createOrder($builder, $customers->get(1));
        $builder->addWholeChickenLine($stop2, $heleKip, $customers->get(1), 3, $variant(1000), $kipSupplierDefault);
        $builder->addWholeChickenLine($stop2, $heleKip, $customers->get(1), 1, $variant(1500), $kipSupplierAlt ?? $kipSupplierDefault);
        $builder->addStandardLine($stop2, $kippenvleugels, $customers->get(1), 2, null, $albertHeijn);
        $builder->recalculateTotal($stop2);
        $this->attachStop($route, $stop2, 2);

        // Stop 3 — hele kip 1200 g (2 doos, tweede leverancier), Kipfilet via standaard leverancier.
        $stop3 = $this->createOrder($builder, $customers->get(2));
        $builder->addWholeChickenLine($stop3, $heleKip, $customers->get(2), 2, $variant(1200), $kipSupplierAlt ?? $kipSupplierDefault);
        $builder->addStandardLine($stop3, $kipfilet, $customers->get(2), 1);
        $builder->recalculateTotal($stop3);
        $this->attachStop($route, $stop3, 3);

        $this->command?->info("Testroute #{$route->id} aangemaakt voor {$driver->email} (vandaag, status PLANNED).");
        $this->command?->info('Hele-kip breakdown: 1000 g → 5 doos · 1200 g → 3 doos · 1500 g → 1 doos.');
        $this->command?->info('Leverancier per variant: 1000 g één leverancier, 1200 g twee leveranciers, 1500 g de tweede leverancier.');
    }

    private function createOrder(DemoOrderBuilder $builder, Customer $customer): Order
    {
        return $builder->createPlacedOrder(
            $customer,
            DemoOrderBuilder::uniqueOrderNumber(self::ORDER_PREFIX),
            today(),
        );
    }

    private function attachStop(Route $route, Order $order, int $stopOrder): RouteStop
    {
        return RouteStop::create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
            'status' => RouteStopStatus::PENDING,
        ]);
    }

    /**
     * Koppelt een tweede leverancier aan hele kip (indien nog niet aanwezig),
     * puur zodat gramvarianten in de test van verschillende leveranciers komen.
     */
    private function ensureAlternateChickenSupplier(Product $heleKip): ?ProductSupplier
    {
        $default = $heleKip->defaultProductSupplier();

        $alternateSupplier = Supplier::query()
            ->where('name', 'Dutch Chicken BV')
            ->orWhere('name', 'Kip Express BV')
            ->when($default, fn ($q) => $q->where('id', '!=', $default->supplier_id))
            ->first();

        if ($alternateSupplier === null) {
            return null;
        }

        return ProductSupplier::query()->firstOrCreate(
            ['product_id' => $heleKip->id, 'supplier_id' => $alternateSupplier->id],
            ['price_per_kg' => 6.75, 'is_default' => false, 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function cleanupPreviousTestData(): void
    {
        $orderIds = Order::query()
            ->where('order_number', 'like', self::ORDER_PREFIX.'-%')
            ->pluck('id');

        if ($orderIds->isEmpty()) {
            return;
        }

        $routeIds = RouteStop::query()->whereIn('order_id', $orderIds)->pluck('route_id')->unique();

        RouteStop::query()->whereIn('order_id', $orderIds)->delete();
        OrderItem::query()->whereIn('order_id', $orderIds)->delete();
        Order::query()->whereIn('id', $orderIds)->delete();

        Route::query()
            ->whereIn('id', $routeIds)
            ->whereDoesntHave('routeStops')
            ->delete();
    }
}
