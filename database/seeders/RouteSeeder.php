<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\OrderItemLoadingService;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RouteSeeder extends Seeder
{
    public function run(): void
    {
        $vehicle = Vehicle::query()->firstOrFail();
        $builder = app(DemoOrderBuilder::class);
        $customers = Customer::query()->orderBy('id')->get();
        $standardProducts = $this->standardProducts();
        $wholeChicken = Product::query()
            ->where('product_type', ProductType::WholeChicken)
            ->with(['activeGramVariants', 'activeProductSuppliers.supplier'])
            ->first();

        $driverLoad = User::query()->where('email', 'driver@koylu.test')->firstOrFail();
        $driverLoading = User::query()->where('email', 'driver2@koylu.test')->firstOrFail();
        $driverDeliver = User::query()->where('email', 'driver3@koylu.test')->firstOrFail();
        $driverPartial = User::query()->where('email', 'driver4@koylu.test')->firstOrFail();

        $this->seedRouteReadyToLoad($builder, $driverLoad, $vehicle, $customers->take(3), $standardProducts, $wholeChicken);
        $this->seedRouteLoadingInProgress($builder, $driverLoading, $vehicle, $customers->slice(3, 2), $standardProducts, $wholeChicken);
        $this->seedRouteReadyToDeliver($builder, $driverDeliver, $vehicle, $customers->slice(1, 3), $standardProducts, $wholeChicken);
        $this->seedRoutePartiallyDelivered($builder, $driverPartial, $vehicle, $customers->slice(2, 3), $standardProducts, $wholeChicken);
        $this->seedRouteCompleted($builder, $driverLoad, $vehicle, $customers->take(2), $standardProducts, $wholeChicken);
        $this->seedFutureRoute($builder, $driverLoading, $vehicle, $customers->slice(4, 1), $standardProducts, $wholeChicken);
    }

    /**
     * @return Collection<int, Product>
     */
    protected function standardProducts(): Collection
    {
        return Product::query()
            ->where('product_type', ProductType::Standard)
            ->with(['activePackagings', 'activeProductSuppliers.supplier'])
            ->get()
            ->filter(fn (Product $product) => $product->defaultPackaging() && $product->defaultProductSupplier());
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedRouteReadyToLoad(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today(), RouteStatus::PLANNED);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index, 'DEMO-LOAD');
            $this->attachStop($route, $order, $index + 1);
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedRouteLoadingInProgress(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today(), RouteStatus::IN_PROGRESS, [
            'started_at' => now()->subMinutes(20),
        ]);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index + 10, 'DEMO-LOADING');
            $this->attachStop($route, $order, $index + 1);
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedRouteReadyToDeliver(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today(), RouteStatus::IN_PROGRESS, [
            'started_at' => now()->subHour(),
            'loading_completed_at' => now()->subMinutes(30),
        ]);

        $loadingService = app(OrderItemLoadingService::class);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index + 20, 'DEMO-DELIVER');
            $this->attachStop($route, $order, $index + 1);

            foreach ($order->items as $item) {
                if ($item->isWholeChicken()) {
                    $loadingService->initializeFromOrder($item->fresh(['productGramVariant']));
                }
            }
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedRoutePartiallyDelivered(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today(), RouteStatus::IN_PROGRESS, [
            'started_at' => now()->subHours(2),
            'loading_completed_at' => now()->subHours(1),
        ]);

        $loadingService = app(OrderItemLoadingService::class);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index + 30, 'DEMO-PARTIAL');
            $stop = $this->attachStop($route, $order, $index + 1);

            foreach ($order->items as $item) {
                if ($item->isWholeChicken()) {
                    if ($index === 0 && $wholeChicken && $wholeChicken->allows_loading_substitute) {
                        $alternate = $wholeChicken->activeGramVariants
                            ->first(fn (ProductGramVariant $v) => $v->id !== $item->product_gram_variant_id)
                            ?? $item->productGramVariant;

                        $loadingService->recordLoadedVariant($item, $alternate, 'Demo: andere grammaat geladen');
                    } else {
                        $loadingService->initializeFromOrder($item->fresh(['productGramVariant']));
                    }
                }
            }

            if ($index === 0) {
                $this->createDeliveryForStop($stop, $order->load('customer'));
            }
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedRouteCompleted(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today()->subDay(), RouteStatus::COMPLETED, [
            'started_at' => now()->subDay()->setTime(6, 0),
            'loading_completed_at' => now()->subDay()->setTime(7, 0),
            'completed_at' => now()->subDay()->setTime(12, 0),
        ]);

        $loadingService = app(OrderItemLoadingService::class);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index + 40, 'DEMO-DONE');
            $stop = $this->attachStop($route, $order, $index + 1, RouteStopStatus::DELIVERED);

            foreach ($order->items as $item) {
                if ($item->isWholeChicken()) {
                    $loadingService->initializeFromOrder($item->fresh(['productGramVariant']));
                }
            }

            $this->createDeliveryForStop($stop, $order->load('customer'));
        }
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function seedFutureRoute(
        DemoOrderBuilder $builder,
        User $driver,
        Vehicle $vehicle,
        Collection $customers,
        Collection $standardProducts,
        ?Product $wholeChicken,
    ): void {
        $route = $this->createRoute($driver, $vehicle, today()->addDay(), RouteStatus::PLANNED);

        foreach ($customers as $index => $customer) {
            $order = $this->createRoutedOrder($builder, $customer, $standardProducts, $wholeChicken, $index + 50, 'DEMO-FUTURE');
            $this->attachStop($route, $order, $index + 1);
        }
    }

    /**
     * @param  Collection<int, Product>  $standardProducts
     */
    protected function createRoutedOrder(
        DemoOrderBuilder $builder,
        Customer $customer,
        Collection $standardProducts,
        ?Product $wholeChicken,
        int $seed,
        string $prefix,
    ): Order {
        $order = $builder->createPlacedOrder(
            $customer,
            DemoOrderBuilder::uniqueOrderNumber($prefix),
            today(),
        );

        $builder->addStandardLine(
            $order,
            $standardProducts->values()->get($seed % $standardProducts->count()),
            $customer,
            (float) rand(1, 3),
        );

        if ($wholeChicken && $seed % 2 === 0) {
            $variant = $wholeChicken->activeGramVariants->get($seed % $wholeChicken->activeGramVariants->count());
            $builder->addWholeChickenLine($order, $wholeChicken, $customer, (float) rand(1, 2), $variant);
        }

        $builder->recalculateTotal($order);

        return $order->fresh(['items.productGramVariant', 'items.product']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function createRoute(
        User $driver,
        Vehicle $vehicle,
        \DateTimeInterface $routeDate,
        RouteStatus $status,
        array $extra = [],
    ): Route {
        return Route::create(array_merge([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_date' => $routeDate,
            'status' => $status,
        ], $extra));
    }

    protected function attachStop(
        Route $route,
        Order $order,
        int $stopOrder,
        RouteStopStatus $status = RouteStopStatus::PENDING,
    ): RouteStop {
        return RouteStop::create([
            'route_id' => $route->id,
            'order_id' => $order->id,
            'stop_order' => $stopOrder,
            'status' => $status,
        ]);
    }

    protected function createDeliveryForStop(RouteStop $stop, Order $order): Delivery
    {
        $delivery = Delivery::create([
            'order_id' => $order->id,
            'delivered_at' => now(),
            'receiver_name' => $order->customer->contact_name ?? $order->customer->company_name,
            'status' => DeliveryStatus::DELIVERED,
        ]);

        foreach ($order->items as $item) {
            DeliveryItem::create([
                'delivery_id' => $delivery->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'ordered_quantity' => $item->quantity,
                'delivered_quantity' => $item->quantity,
            ]);
        }

        $stop->update(['status' => RouteStopStatus::DELIVERED]);
        $order->update(['status' => OrderStatus::DELIVERED]);

        return $delivery;
    }
}
