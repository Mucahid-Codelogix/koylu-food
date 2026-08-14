<?php

use App\Enums\DeliveryStatus;
use App\Filament\Pages\Settings as SettingsPage;
use App\Models\AppSetting;
use App\Models\CrateTransaction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\InvoiceLineCalculator;
use App\Services\InvoiceService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{0: Order, 1: Delivery, 2: Route}
 */
function deliveredOrderWithCrates(int $given, int $returned, array $customerAttributes = []): array
{
    $customer = Customer::factory()->create([
        'is_vat_exempt' => false,
        ...$customerAttributes,
    ]);

    $route = Route::factory()->create([
        'driver_id' => User::factory()->driver()->create()->id,
    ]);

    $order = Order::factory()->placed()->for($customer)->create();

    $orderItem = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => Product::factory()->create(['name' => 'Kipfilet'])->id,
        'product_name' => 'Kipfilet',
        'quantity' => 2,
        'box_weight_kg' => 10,
        'price_per_kg' => 5,
        'vat_rate' => 9,
    ]);

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
    ]);

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $orderItem->id,
        'product_id' => $orderItem->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    if ($given > 0 || $returned > 0) {
        CrateTransaction::create([
            'customer_id' => $customer->id,
            'route_id' => $route->id,
            'crates_given' => $given,
            'crates_returned' => $returned,
        ]);
    }

    return [$order->fresh(['items', 'customer', 'routeStops']), $delivery->fresh('items'), $route];
}

beforeEach(function () {
    app(AppSettings::class)->flush();
    app(AppSettings::class)->set(AppSettings::CRATE_DEPOSIT_PRICE, 0.25);
});

it('zet meegegeven kratten als plusregel en retour als minregel op de factuur', function () {
    [$order, $delivery] = deliveredOrderWithCrates(given: 10, returned: 4);

    $lines = app(InvoiceLineCalculator::class)->lines($order, $delivery);
    $crateLines = $lines->where('type', InvoiceLineCalculator::TYPE_CRATE_DEPOSIT)->values();

    expect($crateLines)->toHaveCount(2)
        ->and($crateLines[0]['product_name'])->toBe('Statiegeld kratten')
        ->and($crateLines[0]['billing_quantity'])->toBe(10.0)
        ->and($crateLines[0]['line_subtotal'])->toBe(2.5)
        ->and($crateLines[1]['product_name'])->toBe('Statiegeld kratten retour')
        ->and($crateLines[1]['billing_quantity'])->toBe(-4.0)
        ->and($crateLines[1]['line_subtotal'])->toBe(-1.0);
});

it('rekent geen btw over statiegeld', function () {
    [$order, $delivery] = deliveredOrderWithCrates(given: 10, returned: 4);

    $calculator = app(InvoiceLineCalculator::class);
    $crateLines = $calculator->lines($order, $delivery)
        ->where('type', InvoiceLineCalculator::TYPE_CRATE_DEPOSIT);

    expect($crateLines->pluck('vat_rate')->unique()->all())->toBe([0.0])
        ->and($crateLines->pluck('line_vat')->unique()->all())->toBe([0.0]);

    $totals = $calculator->totals($order, $delivery);
    $zeroRateGroup = collect($totals['vat_by_rate'])->firstWhere('rate', 0.0);

    // 100 kg × € 5 = € 100 producten, plus € 2,50 -/- € 1,00 statiegeld.
    expect($totals['subtotal'])->toBe(101.5)
        ->and($totals['vat'])->toBe(9.0)
        ->and($totals['total'])->toBe(110.5)
        ->and($zeroRateGroup['taxable_amount'])->toBe(1.5)
        ->and($zeroRateGroup['vat_amount'])->toBe(0.0);
});

it('gebruikt de klantprijs boven de algemene statiegeldprijs', function () {
    [$order, $delivery] = deliveredOrderWithCrates(
        given: 10,
        returned: 0,
        customerAttributes: ['crate_deposit_price' => 0.50],
    );

    $crateLine = app(InvoiceLineCalculator::class)
        ->lines($order, $delivery)
        ->firstWhere('type', InvoiceLineCalculator::TYPE_CRATE_DEPOSIT);

    expect($crateLine['billing_unit_price'])->toBe(0.5)
        ->and($crateLine['line_subtotal'])->toBe(5.0);
});

it('laat statiegeld weg als er geen prijs is ingesteld', function () {
    app(AppSettings::class)->set(AppSettings::CRATE_DEPOSIT_PRICE, 0);

    [$order, $delivery] = deliveredOrderWithCrates(given: 10, returned: 4);

    expect(app(InvoiceLineCalculator::class)->crateDepositLines($order, $delivery))->toBeEmpty();
});

it('laat statiegeld weg als er geen kratten zijn geregistreerd', function () {
    [$order, $delivery] = deliveredOrderWithCrates(given: 0, returned: 0);

    expect(app(InvoiceLineCalculator::class)->crateDepositLines($order, $delivery))->toBeEmpty();
});

it('neemt statiegeld mee in het factuurbedrag', function () {
    [$order, $delivery] = deliveredOrderWithCrates(given: 10, returned: 4);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery);

    expect((float) $invoice->subtotal_amount)->toBe(101.5)
        ->and((float) $invoice->total_amount)->toBe(110.5);
});

it('toont statiegeld op de factuur-pdf en in de ubl', function () {
    [$order, $delivery] = deliveredOrderWithCrates(given: 10, returned: 4);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery);

    $service->generatePdf($invoice);
    $service->generateUbl($invoice->fresh());

    $ubl = UploadStorage::disk()->get($invoice->fresh()->ubl_path);

    expect($ubl)->toContain('Statiegeld kratten')
        ->toContain('Statiegeld kratten retour')
        ->toContain('unitCode="H87"')
        ->and(UploadStorage::disk()->exists($invoice->fresh()->pdf_path))->toBeTrue();
});

it('slaat de statiegeldprijs op via de instellingenpagina', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(SettingsPage::class)
        ->assertSuccessful()
        ->fillForm([AppSettings::CRATE_DEPOSIT_PRICE => 0.40])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(AppSettings::class)->crateDepositPrice())->toBe(0.4);
});

it('bewaart de algemene statiegeldprijs via de instellingen', function () {
    $settings = app(AppSettings::class);

    $settings->set(AppSettings::CRATE_DEPOSIT_PRICE, 0.35);

    expect($settings->crateDepositPrice())->toBe(0.35)
        ->and(AppSetting::query()->where('key', AppSettings::CRATE_DEPOSIT_PRICE)->value('value'))->toBe('0.35');
});
