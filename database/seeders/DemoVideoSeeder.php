<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Enums\UserRole;
use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGramVariant;
use App\Models\ProductSupplier;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AppSettings;
use Database\Seeders\Support\DemoOrderBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Zet de complete uitgangssituatie klaar voor de klantdemo-video
 * (draaiboek "Koylu Demo Draaiboek", scènes 1 t/m 8).
 *
 * Wat deze seeder klaarzet:
 *  - statiegeld globaal €0,25; klant A zonder eigen prijs, klant B met €0,50
 *  - hele kip met twee leveranciers en substitutie tijdens laden toegestaan
 *  - eigen chauffeursaccount met precies één route van vandaag, status PLANNED
 *  - stop 1 (klant A): hele kip 1200 g + 1500 g van twee leveranciers + doosproduct
 *  - stop 2 (klant B): hele kip 1000 g + een tweede doosproduct
 *
 * De demo maakt zelf de leveringen en facturen aan; die worden hier bewust
 * niet geseed.
 *
 * Veilig op een testserver:
 *  - raakt alleen eigen data aan (DEMO-VIDEO-orders, eigen chauffeur, twee
 *    klanten die op naam worden gezocht of aangemaakt)
 *  - gebruikt geen factories, want fakerphp/faker is een dev-dependency en
 *    ontbreekt bij een deploy met composer install --no-dev
 *  - demo-only velden worden zonder model-events opgeslagen, zodat er geen
 *    Exact-synchronisatie voor wordt ingepland
 *
 * Idempotent: eerdere DEMO-VIDEO-data wordt eerst opgeruimd, inclusief de
 * leveringen en facturen die tijdens een vorige opname zijn ontstaan.
 *
 * Draaien:  php artisan db:seed --class=DemoVideoSeeder --force
 */
class DemoVideoSeeder extends Seeder
{
    private const ORDER_PREFIX = 'DEMO-VIDEO';

    private const DRIVER_EMAIL = 'demo@koylu.test';

    private const GLOBAL_CRATE_PRICE = 0.25;

    private const CUSTOMER_B_CRATE_PRICE = 0.50;

    private const CUSTOMER_A_NAME = 'Restaurant De Gouden Kip';

    private const CUSTOMER_B_NAME = 'Slagerij Van Berg';

    public function run(): void
    {
        $this->cleanupPreviousDemoData();

        app(AppSettings::class)->set(AppSettings::CRATE_DEPOSIT_PRICE, self::GLOBAL_CRATE_PRICE);

        $driver = $this->demoDriver();
        $vehicle = $this->demoVehicle();

        $customerA = $this->demoCustomer(self::CUSTOMER_A_NAME, null, 'Mark Jansen', 'Kerkstraat 12', '9711 AA', 'Groningen');
        $customerB = $this->demoCustomer(self::CUSTOMER_B_NAME, self::CUSTOMER_B_CRATE_PRICE, 'Petra van Berg', 'Marktplein 5', '9401 AB', 'Assen');

        $userA = $this->portalUser($customerA, 1);
        $userB = $this->portalUser($customerB, 2);

        $heleKip = $this->wholeChicken();
        [$productA, $productB] = $this->standardProducts();

        $kipDefault = $heleKip->defaultProductSupplier();
        $kipAlternate = $this->alternateSupplier($heleKip) ?? $kipDefault;
        $productAsupplier = $this->alternateSupplier($productA);

        $route = Route::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'route_date' => today(),
            'status' => RouteStatus::PLANNED,
        ]);

        $builder = app(DemoOrderBuilder::class);

        // Stop 1 — klant zonder eigen statiegeldprijs. Twee gramvarianten van
        // twee leveranciers, zodat scène 4 iets te laten zien heeft. Deze stop
        // wordt in scène 5 contant afgerekend.
        $orderA = $builder->createPlacedOrder(
            $customerA,
            self::ORDER_PREFIX.'-1',
            today(),
            'Demo-video stop 1 — contant afrekenen.',
        );
        $builder->addWholeChickenLine($orderA, $heleKip, $customerA, 2, $this->variant($heleKip, 1200), $kipDefault);
        $builder->addWholeChickenLine($orderA, $heleKip, $customerA, 1, $this->variant($heleKip, 1500), $kipAlternate);
        $builder->addStandardLine($orderA, $productA, $customerA, 3, null, $productAsupplier);
        $builder->recalculateTotal($orderA);
        $this->attachStop($route, $orderA, 1);

        // Stop 2 — klant mét eigen statiegeldprijs. Normaal afrekenen, zodat er
        // in scène 7 een conceptfactuur klaarstaat met €0,50 per krat.
        $orderB = $builder->createPlacedOrder(
            $customerB,
            self::ORDER_PREFIX.'-2',
            today(),
            'Demo-video stop 2 — niet contant, blijft concept voor Exact.',
        );
        $builder->addWholeChickenLine($orderB, $heleKip, $customerB, 2, $this->variant($heleKip, 1000), $kipDefault);
        $builder->addStandardLine($orderB, $productB, $customerB, 2);
        $builder->recalculateTotal($orderB);

        $this->attachStop($route, $orderB, 2);

        $this->report($route, $driver, $vehicle, $customerA, $customerB, $userA, $userB, $heleKip, $productA, $productB);
    }

    private function demoDriver(): User
    {
        $driver = User::query()->firstOrCreate(
            ['email' => self::DRIVER_EMAIL],
            [
                'name' => 'Demo Chauffeur — video',
                'password' => Hash::make('password'),
                'role' => UserRole::DRIVER,
                'email_verified_at' => now(),
            ],
        );

        // Wachtwoord terugzetten, zodat de demo-login ook werkt als iemand hem
        // eerder heeft gewijzigd.
        $driver->forceFill([
            'password' => Hash::make('password'),
            'role' => UserRole::DRIVER,
        ])->save();

        return $driver;
    }

    /**
     * Een eigen chauffeur houdt het dashboard schoon: precies één route van
     * vandaag, ook als er al andere demo-routes in de database staan.
     */
    private function demoVehicle(): Vehicle
    {
        $vehicle = Vehicle::query()->orderBy('id')->first();

        if ($vehicle !== null) {
            return $vehicle;
        }

        return Vehicle::query()->create([
            'brand' => 'Mercedes-Benz',
            'model' => 'Sprinter Koelwagen',
            'license_plate' => 'VD-001-K',
        ]);
    }

    /**
     * Zoekt de demoklant op bedrijfsnaam en maakt hem aan als hij ontbreekt.
     * Zo raakt de seeder nooit een willekeurige bestaande klant.
     */
    private function demoCustomer(
        string $companyName,
        ?float $crateDepositPrice,
        string $contactName,
        string $address,
        string $postalCode,
        string $city,
    ): Customer {
        $customer = Customer::query()->firstOrCreate(
            ['company_name' => $companyName],
            [
                'contact_name' => $contactName,
                'email' => 'demo+'.str($companyName)->slug().'@koylu.test',
                'phone' => '050-1234567',
                'address' => $address,
                'postal_code' => $postalCode,
                'city' => $city,
                'is_active' => true,
            ],
        );

        // De statiegeldprijs is puur demo-inrichting en hoort niet in Exact,
        // dus zonder events opslaan: dat scheelt een SyncCustomerToExact-job.
        Customer::withoutEvents(function () use ($customer, $crateDepositPrice): void {
            $customer->forceFill([
                'crate_deposit_price' => $crateDepositPrice,
                'is_active' => true,
            ])->save();
        });

        return $customer->refresh();
    }

    /**
     * Een eigen portaalaccount per demoklant, zodat de login in scène 6 altijd
     * werkt — ook als de klant al een echt account heeft met een ander wachtwoord.
     */
    private function portalUser(Customer $customer, int $number): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => "demo-klant{$number}@koylu.test"],
            [
                'name' => $customer->contact_name ?? $customer->company_name,
                'customer_id' => $customer->id,
                'password' => Hash::make('password'),
                'role' => UserRole::CUSTOMER,
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill([
            'customer_id' => $customer->id,
            'password' => Hash::make('password'),
            'role' => UserRole::CUSTOMER,
        ])->save();

        return $user;
    }

    /**
     * Hele kip met de drie gramvarianten die het draaiboek noemt en een tweede
     * leverancier, zodat scène 4 varianten én leveranciers laat zien.
     */
    private function wholeChicken(): Product
    {
        $heleKip = Product::query()
            ->where('product_type', ProductType::WholeChicken)
            ->orderBy('id')
            ->first();

        if ($heleKip === null) {
            $this->command?->warn('Geen hele-kipproduct gevonden, ProductSeeder wordt gedraaid.');
            $this->callSilent(SupplierSeeder::class);
            $this->callSilent(ProductSeeder::class);

            $heleKip = Product::query()
                ->where('product_type', ProductType::WholeChicken)
                ->orderBy('id')
                ->firstOr(fn () => throw new \RuntimeException('Kon geen hele-kipproduct aanmaken.'));
        }

        // Zonder deze vlag krijgt de chauffeur in scène 4 de melding
        // "Alternatief niet toegestaan voor dit product". Puur een demo-instelling,
        // dus zonder events opslaan om een Exact-sync te vermijden.
        Product::withoutEvents(function () use ($heleKip): void {
            $heleKip->forceFill(['allows_loading_substitute' => true, 'is_active' => true])->save();
        });

        foreach ([
            ['weight_grams' => 1000, 'pieces_per_box' => 11, 'box_weight_kg' => 11.50],
            ['weight_grams' => 1200, 'pieces_per_box' => 10, 'box_weight_kg' => 12.35],
            ['weight_grams' => 1500, 'pieces_per_box' => 8, 'box_weight_kg' => 12.50],
        ] as $index => $data) {
            ProductGramVariant::query()->firstOrCreate(
                ['product_id' => $heleKip->id, 'weight_grams' => $data['weight_grams']],
                [
                    'pieces_per_box' => $data['pieces_per_box'],
                    'box_weight_kg' => $data['box_weight_kg'],
                    'sort_order' => 10 + $index,
                    'is_default' => false,
                    'is_active' => true,
                ],
            );
        }

        $this->ensureTwoSuppliers($heleKip, 6.50, 6.75);

        return $heleKip->fresh(['gramVariants', 'productSuppliers']);
    }

    /**
     * Twee gewone doosproducten voor de rest van de order.
     *
     * @return array{0: Product, 1: Product}
     */
    private function standardProducts(): array
    {
        $products = Product::query()
            ->where('product_type', '!=', ProductType::WholeChicken)
            ->where('is_active', true)
            ->whereHas('packagings')
            ->whereHas('productSuppliers')
            ->orderByRaw("CASE WHEN LOWER(name) = 'kipfilet' THEN 0 WHEN LOWER(name) = 'kippenvleugels' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->take(2)
            ->get();

        if ($products->count() < 2) {
            throw new \RuntimeException(
                'Er zijn minstens twee gewone producten met verpakking en leverancier nodig. Draai eerst: php artisan db:seed --class=ProductSeeder --force',
            );
        }

        /** @var Product $first */
        $first = $products->get(0);
        /** @var Product $second */
        $second = $products->get(1);

        // Het eerste product krijgt een tweede leverancier, zodat de chauffeur in
        // scène 4 ook bij een gewoon product de leverancier ziet staan.
        $this->ensureTwoSuppliers($first, 9.95, 10.50);

        return [$first->fresh(['productSuppliers']), $second];
    }

    private function ensureTwoSuppliers(Product $product, float $defaultPrice, float $alternatePrice): void
    {
        $default = $product->defaultProductSupplier();

        if ($default === null) {
            $supplier = Supplier::query()->orderBy('id')->first()
                ?? Supplier::query()->create(['name' => 'Pluimveehandel Noord', 'is_active' => true]);

            $default = ProductSupplier::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'price_per_kg' => $defaultPrice,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }

        $second = Supplier::query()
            ->where('id', '!=', $default->supplier_id)
            ->orderBy('id')
            ->first()
            ?? Supplier::query()->create(['name' => 'Kip Express BV', 'is_active' => true]);

        ProductSupplier::query()->firstOrCreate(
            ['product_id' => $product->id, 'supplier_id' => $second->id],
            ['price_per_kg' => $alternatePrice, 'is_default' => false, 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function variant(Product $product, int $grams): ProductGramVariant
    {
        $match = $product->gramVariants->firstWhere('weight_grams', $grams);

        if ($match === null) {
            throw new \RuntimeException("Gramvariant {$grams} g ontbreekt bij {$product->name}.");
        }

        return $match;
    }

    private function alternateSupplier(Product $product): ?ProductSupplier
    {
        return ProductSupplier::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->where('is_default', false)
            ->orderBy('sort_order')
            ->first();
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
     * Ruimt de vorige opname op: orders met het DEMO-VIDEO-prefix en alles wat
     * daaraan hangt, plus de routes van de demo-chauffeur en de kratmutaties die
     * tijdens het leveren zijn geboekt. Andere data blijft ongemoeid.
     */
    private function cleanupPreviousDemoData(): void
    {
        $orderIds = Order::query()
            ->where('order_number', 'like', self::ORDER_PREFIX.'-%')
            ->pluck('id');

        $routeIds = RouteStop::query()->whereIn('order_id', $orderIds)->pluck('route_id')->unique();

        $driver = User::query()->where('email', self::DRIVER_EMAIL)->first();

        if ($driver !== null) {
            $routeIds = $routeIds
                ->merge(Route::query()->where('driver_id', $driver->id)->pluck('id'))
                ->unique();
        }

        if ($routeIds->isNotEmpty()) {
            CrateTransaction::query()->whereIn('route_id', $routeIds)->delete();
        }

        if ($orderIds->isNotEmpty()) {
            // Leveringen, facturen en stops zitten met RESTRICT aan orders vast,
            // dus die moeten er eerst uit. delivery_items en order_items volgen
            // wel vanzelf via cascade.
            Invoice::query()->whereIn('order_id', $orderIds)->delete();
            Delivery::query()->whereIn('order_id', $orderIds)->delete();
            RouteStop::query()->whereIn('order_id', $orderIds)->delete();
            Order::query()->whereIn('id', $orderIds)->delete();
        }

        Route::query()->whereIn('id', $routeIds)->delete();
    }

    private function report(
        Route $route,
        User $driver,
        Vehicle $vehicle,
        Customer $customerA,
        Customer $customerB,
        User $userA,
        User $userB,
        Product $heleKip,
        Product $productA,
        Product $productB,
    ): void {
        $admin = User::query()->where('role', UserRole::ADMIN)->orderBy('id')->first();

        $this->command?->newLine();
        $this->command?->info('Demo-video data klaargezet. Wachtwoord van de demo-accounts: password');

        $this->command?->table(
            ['Scène', 'Wie', 'Login', 'Wat je daar toont'],
            [
                ['1, 2, 3, 7, 8', 'Admin', $admin?->email ?? 'geen admin gevonden', 'Instellingen, route, leverbonnen, facturen'],
                ['4, 5', 'Chauffeur', $driver->email, "Route #{$route->id} van vandaag, status Gepland"],
                ['6', 'Klant stop 1', $userA->email, $customerA->company_name.' — contante factuur'],
                ['6 (optie)', 'Klant stop 2', $userB->email, $customerB->company_name.' — statiegeld € 0,50'],
            ],
        );

        $this->command?->table(
            ['Stop', 'Klant', 'Statiegeld', 'Order', 'Regels'],
            [
                [
                    '1',
                    $customerA->company_name,
                    'globaal € '.number_format(self::GLOBAL_CRATE_PRICE, 2, ',', '.'),
                    self::ORDER_PREFIX.'-1',
                    "{$heleKip->name} 1200 g (2 doos) + 1500 g (1 doos, 2e leverancier) + {$productA->name} (3)",
                ],
                [
                    '2',
                    $customerB->company_name,
                    'eigen € '.number_format(self::CUSTOMER_B_CRATE_PRICE, 2, ',', '.'),
                    self::ORDER_PREFIX.'-2',
                    "{$heleKip->name} 1000 g (2 doos) + {$productB->name} (2)",
                ],
            ],
        );

        $this->command?->line("Voertuig: {$vehicle->license_plate}");

        if ($admin === null) {
            $this->command?->warn('Er staat geen adminaccount in deze database — log met je eigen beheerder in voor scène 1, 2, 3, 7 en 8.');
        }

        $this->command?->line('Stop 1 in scène 5 contant afrekenen, stop 2 niet — dan heb je in scène 8 beide gevallen.');
        $this->command?->newLine();
    }
}
