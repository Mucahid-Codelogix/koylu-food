<?php

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Actions\InvoicePdfAction;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\InvoiceLineCalculator;
use App\Services\InvoiceService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{invoice: Invoice, html: string}
 */
function renderInvoicePdfHtml(array $lineSpecs, bool $vatExempt = false): array
{
    $customer = Customer::factory()->create(['is_vat_exempt' => $vatExempt]);
    $order = Order::factory()->for($customer)->create();
    $delivery = Delivery::factory()->create(['order_id' => $order->id]);

    foreach ($lineSpecs as $spec) {
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_name' => $spec['product_name'] ?? fake()->words(2, true),
            'unit' => $spec['unit'] ?? 'Doos 10 kg',
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

    $order = $order->fresh(['items', 'customer', 'delivery.items']);
    $delivery = $delivery->fresh('items');
    $calculator = app(InvoiceLineCalculator::class);
    $lines = $calculator->lines($order, $delivery);
    $totals = $calculator->totals($order, $delivery);

    $invoice = Invoice::factory()->create([
        'order_id' => $order->id,
        'subtotal_amount' => $totals['subtotal'],
        'vat_amount' => $totals['vat'],
        'total_amount' => $totals['total'],
    ]);

    $html = view('pdf.invoice', [
        'invoice' => $invoice->load('order.customer', 'order.delivery'),
        'lines' => $lines,
        'vatByRate' => $totals['vat_by_rate'],
    ])->render();

    return ['invoice' => $invoice, 'html' => $html];
}

it('shows delivered kg and price per kg on invoice lines', function () {
    ['html' => $html] = renderInvoicePdfHtml([
        [
            'product_name' => 'Kipfilet',
            'delivered_quantity' => 4,
            'box_weight_kg' => 2.5,
            'price_per_kg' => 5.02,
            'vat_rate' => 21,
        ],
    ]);

    expect($html)
        ->toContain('Geleverd (kg)')
        ->toContain('Prijs/kg')
        ->toContain('€ 5,0200')
        ->toContain('10')
        ->not->toContain('Stukprijs');
});

it('shows vat split per rate in the totals section', function () {
    ['html' => $html] = renderInvoicePdfHtml([
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

    expect($html)
        ->toContain('BTW (9%)')
        ->toContain('BTW (21%)')
        ->toContain('€ 9,00')
        ->toContain('€ 10,50');
});

it('shows zero percent vat label for exempt customers', function () {
    ['html' => $html] = renderInvoicePdfHtml([
        [
            'delivered_quantity' => 2,
            'box_weight_kg' => 5,
            'price_per_kg' => 10,
            'vat_rate' => 21,
        ],
    ], vatExempt: true);

    expect($html)
        ->toContain('BTW (0% — vrijgesteld)')
        ->toContain('€ 0,00');
});

it('generates a pdf file through the invoice service', function () {
    Storage::fake(UploadStorage::diskName());

    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create();
    $delivery = Delivery::factory()->create(['order_id' => $order->id]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'quantity' => 2,
        'box_weight_kg' => 5,
        'price_per_kg' => 10,
        'vat_rate' => 21,
        'unit_price' => 50,
        'subtotal' => 100,
    ]);

    DeliveryItem::query()->create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $order->items()->first()->id,
        'product_id' => $order->items()->first()->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    $service = app(InvoiceService::class);
    $invoice = $service->createFromDelivery($delivery->fresh('items'));
    $service->generatePdf($invoice->fresh(['order.customer', 'order.items', 'order.delivery.items']));

    $invoice->refresh();

    expect($invoice->pdf_path)->not->toBeNull();
    Storage::disk(UploadStorage::diskName())->assertExists($invoice->pdf_path);
});

it('keeps concept status when opening a concept pdf', function () {
    Storage::fake(UploadStorage::diskName());

    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create();
    $delivery = Delivery::factory()->create(['order_id' => $order->id]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'quantity' => 2,
        'box_weight_kg' => 5,
        'price_per_kg' => 10,
        'vat_rate' => 21,
        'unit_price' => 50,
        'subtotal' => 100,
    ]);

    DeliveryItem::query()->create([
        'delivery_id' => $delivery->id,
        'order_item_id' => $order->items()->first()->id,
        'product_id' => $order->items()->first()->product_id,
        'ordered_quantity' => 2,
        'delivered_quantity' => 2,
    ]);

    $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

    InvoicePdfAction::generateForConcept($invoice->fresh(['order.customer', 'order.items', 'order.delivery.items']));

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::CONCEPT)
        ->and($invoice->pdf_path)->not->toBeNull()
        ->and($invoice->ubl_path)->toBeNull()
        ->and($invoice->sent_at)->toBeNull();

    Storage::disk(UploadStorage::diskName())->assertExists($invoice->pdf_path);
});
