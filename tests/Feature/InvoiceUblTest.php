<?php

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceLineCalculator;
use App\Services\InvoiceService;
use App\Services\InvoiceUblBuilder;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{order: Order, delivery: Delivery}
 */
function makeUblOrder(array $lineSpecs, bool $vatExempt = false): array
{
    $customer = Customer::factory()->create(['is_vat_exempt' => $vatExempt]);
    $order = Order::factory()->for($customer)->create();
    $delivery = Delivery::factory()->create(['order_id' => $order->id]);

    foreach ($lineSpecs as $spec) {
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => $spec['product_name'] ?? fake()->words(2, true),
            'quantity' => $spec['quantity'] ?? 1,
            'box_weight_kg' => $spec['box_weight_kg'],
            'price_per_kg' => $spec['price_per_kg'],
            'vat_rate' => $spec['vat_rate'] ?? 21,
            'unit_price' => $spec['box_weight_kg'] * $spec['price_per_kg'],
            'subtotal' => ($spec['quantity'] ?? 1) * $spec['box_weight_kg'] * $spec['price_per_kg'],
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'ordered_quantity' => $spec['quantity'] ?? 1,
            'delivered_quantity' => $spec['delivered_quantity'],
            'missed_reason' => ($spec['delivered_quantity'] ?? 0) == 0 ? ($spec['missed_reason'] ?? null) : null,
        ]);
    }

    return [
        'order' => $order->fresh(['items', 'customer']),
        'delivery' => $delivery->fresh('items'),
    ];
}

it('maps vat rates to the correct ubl category codes', function () {
    $builder = app(InvoiceUblBuilder::class);

    expect($builder->ublVatCode(0))->toBe('Z')
        ->and($builder->ublVatCode(9))->toBe('AA')
        ->and($builder->ublVatCode(21))->toBe('S');
});

it('exports invoice lines in kg with price per kg and per line vat', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeUblOrder([
        [
            'product_name' => 'Kipfilet',
            'delivered_quantity' => 4,
            'box_weight_kg' => 2.5,
            'price_per_kg' => 5.02,
            'vat_rate' => 21,
        ],
    ]);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery);
    $service->generateUbl($invoice->refresh());

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->refresh()->ubl_path);
    $doc = ublDocument($ubl);

    expect($ubl)
        ->toContain('unitCode="KGM"')
        ->toContain('<cbc:InvoicedQuantity unitCode="KGM">10</cbc:InvoicedQuantity>')
        ->toContain('<cbc:PriceAmount currencyID="EUR">5.0200</cbc:PriceAmount>')
        ->toContain('<cbc:Percent>21</cbc:Percent>')
        ->toContain('<cbc:ID>S</cbc:ID>');

    $lineAmount = (float) $doc->xpath('//cac:InvoiceLine/cbc:LineExtensionAmount')[0];
    expect($lineAmount)->toBe(50.20);
});

it('renders one tax subtotal per vat rate', function () {
    Storage::fake(UploadStorage::diskName());

    ['order' => $order, 'delivery' => $delivery] = makeUblOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 9,
        ],
        [
            'delivered_quantity' => 1,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ]);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery);
    $service->generateUbl($invoice->refresh());

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->refresh()->ubl_path);
    $doc = ublDocument($ubl);
    $totals = app(InvoiceLineCalculator::class)->totals($order, $delivery);

    $subtotals = $doc->xpath('//cac:TaxTotal/cac:TaxSubtotal');
    expect($subtotals)->toHaveCount(2);

    $ninePercent = collect($subtotals)->first(
        fn (SimpleXMLElement $node): bool => (string) $node->xpath('cac:TaxCategory/cbc:Percent')[0] === '9'
    );
    $twentyOnePercent = collect($subtotals)->first(
        fn (SimpleXMLElement $node): bool => (string) $node->xpath('cac:TaxCategory/cbc:Percent')[0] === '21'
    );

    expect((string) $ninePercent->xpath('cac:TaxCategory/cbc:ID')[0])->toBe('AA')
        ->and((float) $ninePercent->xpath('cbc:TaxableAmount')[0])->toBe(100.0)
        ->and((float) $ninePercent->xpath('cbc:TaxAmount')[0])->toBe(9.0)
        ->and((string) $twentyOnePercent->xpath('cac:TaxCategory/cbc:ID')[0])->toBe('S')
        ->and((float) $twentyOnePercent->xpath('cbc:TaxableAmount')[0])->toBe(50.0)
        ->and((float) $twentyOnePercent->xpath('cbc:TaxAmount')[0])->toBe(10.50)
        ->and((float) $doc->xpath('//cac:TaxTotal/cbc:TaxAmount')[0])->toBe($totals['vat'])
        ->and($ubl)->toContain('<cbc:Percent>9</cbc:Percent>')
        ->and($ubl)->toContain('<cbc:Percent>21</cbc:Percent>');
});

it('uses zero rated ubl categories for vat exempt customers', function () {
    Storage::fake(UploadStorage::diskName());

    ['delivery' => $delivery] = makeUblOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ], vatExempt: true);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery);
    $service->generateUbl($invoice->refresh());

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->refresh()->ubl_path);
    $doc = ublDocument($ubl);

    expect($ubl)->toContain('<cbc:ID>Z</cbc:ID>')
        ->and($ubl)->toContain('<cbc:Percent>0</cbc:Percent>')
        ->and((float) $doc->xpath('//cac:TaxTotal/cbc:TaxAmount')[0])->toBe(0.0);
});

it('bills missed delivery lines as zero kg in ubl', function () {
    Storage::fake(UploadStorage::diskName());

    ['delivery' => $delivery] = makeUblOrder([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
        [
            'delivered_quantity' => 0,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
            'missed_reason' => 'Niet geleverd',
        ],
    ]);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery);
    $service->generateUbl($invoice->refresh());

    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->refresh()->ubl_path);
    $doc = ublDocument($ubl);

    $quantities = collect($doc->xpath('//cac:InvoiceLine/cbc:InvoicedQuantity'))
        ->map(fn (SimpleXMLElement $node): float => (float) $node)
        ->all();

    expect($quantities)->toBe([10.0, 0.0])
        ->and((float) $invoice->subtotal_amount)->toBe(100.0);
});
