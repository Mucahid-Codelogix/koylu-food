<?php

use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Filament\Driver\Pages\DriverDeliveryPhase;
use App\Jobs\ProcessCashPaidInvoice;
use App\Mail\InvoicePaidMail;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\CashPaymentService;
use App\Services\DeliveryNoteService;
use App\Services\InvoiceService;
use App\Support\UploadStorage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Route, 2: Order, 3: OrderItem}
 */
function routeReadyForDelivery(): array
{
    $driver = User::factory()->driver()->create();
    $route = Route::factory()->create([
        'driver_id' => $driver->id,
        'status' => RouteStatus::IN_PROGRESS,
        'loading_completed_at' => now(),
    ]);

    $order = Order::factory()->placed()->for(Customer::factory()->create([
        'email' => 'klant@example.com',
    ]))->create();

    RouteStop::factory()->create([
        'route_id' => $route->id,
        'order_id' => $order->id,
        'stop_order' => 1,
        'status' => RouteStopStatus::PENDING,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => Product::factory()->create()->id,
        'quantity' => 2,
        'box_weight_kg' => 10,
        'price_per_kg' => 5,
        'vat_rate' => 21,
    ]);

    return [$driver, $route, $order, $item];
}

it('markeert de factuur als contant betaald en zet hem direct op betaald', function () {
    Bus::fake();

    [, , $order] = routeReadyForDelivery();

    $invoice = Invoice::factory()->create([
        'order_id' => $order->id,
        'status' => InvoiceStatus::CONCEPT,
        'total_amount' => 121.00,
    ]);

    app(CashPaymentService::class)->registerCashPayment($invoice, 121.00);

    $invoice->refresh();

    expect($invoice->payment_method)->toBe(PaymentMethod::CASH)
        ->and($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and((float) $invoice->paid_amount)->toBe(121.0);

    Bus::assertDispatched(ProcessCashPaidInvoice::class);
});

it('vult het betaalde bedrag aan met het factuurtotaal als de chauffeur niets invult', function () {
    Bus::fake();

    [, , $order] = routeReadyForDelivery();

    $invoice = Invoice::factory()->create([
        'order_id' => $order->id,
        'status' => InvoiceStatus::CONCEPT,
        'total_amount' => 121.00,
    ]);

    app(CashPaymentService::class)->registerCashPayment($invoice, null);

    expect((float) $invoice->fresh()->paid_amount)->toBe((float) $invoice->fresh()->total_amount);
});

it('registreert een contante betaling bij het afronden van een levering', function () {
    Bus::fake();
    Storage::fake(UploadStorage::diskName());

    [$driver, $route, $order, $item] = routeReadyForDelivery();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    $component->set('signature', 'data:image/png;base64,'.base64_encode('handtekening'));
    $component->set('driverSignature', 'data:image/png;base64,'.base64_encode('handtekening'));
    $component->set('receiverName', 'Jan de Vries');
    $component->set('paidInCash', true);
    $component->set('cashAmount', '121,00');
    $component->call('saveDelivery');

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    expect($invoice->payment_method)->toBe(PaymentMethod::CASH)
        ->and($invoice->status)->toBe(InvoiceStatus::PAID)
        ->and((float) $invoice->paid_amount)->toBe(121.0);

    Bus::assertDispatched(ProcessCashPaidInvoice::class);
});

it('laat een levering zonder contante betaling als concept staan', function () {
    Bus::fake();
    Storage::fake(UploadStorage::diskName());

    [$driver, $route, $order] = routeReadyForDelivery();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    $component->set('signature', 'data:image/png;base64,'.base64_encode('handtekening'));
    $component->set('driverSignature', 'data:image/png;base64,'.base64_encode('handtekening'));
    $component->set('receiverName', 'Jan de Vries');
    $component->call('saveDelivery');

    $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();

    expect($invoice->payment_method)->toBe(PaymentMethod::BANK)
        ->and($invoice->status)->toBe(InvoiceStatus::CONCEPT)
        ->and($invoice->paid_at)->toBeNull();

    Bus::assertNotDispatched(ProcessCashPaidInvoice::class);
});

it('mailt de contante factuur met pdf naar de klant, ook zonder Exact-koppeling', function () {
    Mail::fake();
    Storage::fake(UploadStorage::diskName());

    [, , $order, $item] = routeReadyForDelivery();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    app(CashPaymentService::class)->registerCashPayment($invoice, null);

    $invoice = $invoice->fresh();

    expect($invoice->pdf_path)->not->toBeNull()
        ->and(UploadStorage::disk()->exists($invoice->pdf_path))->toBeTrue()
        ->and($invoice->sent_at)->not->toBeNull();

    Mail::assertSent(InvoicePaidMail::class, function (InvoicePaidMail $mail) use ($invoice): bool {
        return $mail->invoice->is($invoice) && $mail->hasTo('klant@example.com');
    });
});

it('legt zowel de handtekening van de bezorger als van de ontvanger vast', function () {
    Bus::fake();
    Storage::fake(UploadStorage::diskName());

    [$driver, $route, $order] = routeReadyForDelivery();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    $component->set('signature', 'data:image/png;base64,'.base64_encode('ontvanger'));
    $component->set('driverSignature', 'data:image/png;base64,'.base64_encode('bezorger'));
    $component->set('receiverName', 'Jan de Vries');
    $component->call('saveDelivery');

    $delivery = Delivery::query()->where('order_id', $order->id)->firstOrFail();

    expect($delivery->signature_path)->toContain('ontvanger')
        ->and($delivery->driver_signature_path)->toContain('bezorger')
        ->and($delivery->driver_name)->toBe($driver->name)
        ->and(UploadStorage::disk()->exists($delivery->signature_path))->toBeTrue()
        ->and(UploadStorage::disk()->exists($delivery->driver_signature_path))->toBeTrue();

    $signatures = app(DeliveryNoteService::class)
        ->viewData($order->fresh(), $delivery)['signatures'];

    expect($signatures['receiver'])->toStartWith('data:image/png;base64,')
        ->and($signatures['driver'])->toStartWith('data:image/png;base64,');
});

it('slaat de levering niet op zonder handtekening van de bezorger', function () {
    Bus::fake();
    Storage::fake(UploadStorage::diskName());

    [$driver, $route, $order] = routeReadyForDelivery();

    $this->actingAs($driver);
    Filament::setCurrentPanel('driver');

    $component = Testable::create(DriverDeliveryPhase::class, [], ['routeId' => $route->id]);

    $component->set('signature', 'data:image/png;base64,'.base64_encode('ontvanger'));
    $component->set('receiverName', 'Jan de Vries');
    $component->call('saveDelivery');

    expect(Delivery::query()->where('order_id', $order->id)->exists())->toBeFalse();
});

it('maakt de factuur-pdf direct aan, ook als de wachtrij stilstaat', function () {
    Bus::fake();
    Storage::fake(UploadStorage::diskName());

    [, , $order, $item] = routeReadyForDelivery();

    $delivery = Delivery::create([
        'order_id' => $order->id,
        'delivered_at' => now(),
        'receiver_name' => 'Jan de Vries',
        'status' => DeliveryStatus::DELIVERED,
    ]);

    DeliveryItem::create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    app(CashPaymentService::class)->registerCashPayment($invoice, null);

    $invoice = $invoice->fresh();

    expect($invoice->pdf_path)->not->toBeNull()
        ->and(UploadStorage::disk()->exists($invoice->pdf_path))->toBeTrue();

    Bus::assertDispatched(ProcessCashPaidInvoice::class);
});
