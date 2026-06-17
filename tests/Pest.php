<?php

use App\Enums\DeliveryStatus;
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
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function ublDocument(string $xml): SimpleXMLElement
{
    $doc = simplexml_load_string($xml);
    $doc->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
    $doc->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

    return $doc;
}

function sumUblLineTotals(string $xml): float
{
    $doc = ublDocument($xml);

    $sum = 0.0;
    foreach ($doc->xpath('//cac:InvoiceLine/cbc:LineExtensionAmount') as $node) {
        $sum += (float) $node;
    }

    return round($sum, 2);
}

/**
 * @param  array<int, array{
 *     quantity?: float,
 *     box_weight_kg: float,
 *     price_per_kg: float,
 *     vat_rate?: float,
 *     delivered: float,
 *     missed_reason?: ?string,
 * }>  $lineSpecs
 * @return array{order: Order, delivery: Delivery}
 */
function makeInvoiceOrder(array $lineSpecs, bool $vatExempt = false): array
{
    $customer = Customer::factory()->create(['is_vat_exempt' => $vatExempt]);
    $order = Order::factory()->for($customer)->create();

    $delivery = Delivery::factory()->create([
        'order_id' => $order->id,
        'status' => DeliveryStatus::PARTIAL,
    ]);

    foreach ($lineSpecs as $spec) {
        $quantity = $spec['quantity'] ?? 1;

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'quantity' => $quantity,
            'box_weight_kg' => $spec['box_weight_kg'],
            'price_per_kg' => $spec['price_per_kg'],
            'vat_rate' => $spec['vat_rate'] ?? 21,
            'unit_price' => $spec['box_weight_kg'] * $spec['price_per_kg'],
            'subtotal' => $quantity * $spec['box_weight_kg'] * $spec['price_per_kg'],
        ]);

        DeliveryItem::query()->create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'ordered_quantity' => $quantity,
            'delivered_quantity' => $spec['delivered'],
            'missed_reason' => $spec['delivered'] == 0 ? ($spec['missed_reason'] ?? null) : null,
        ]);
    }

    return [
        'order' => $order->fresh(['items', 'customer']),
        'delivery' => $delivery->fresh('items'),
    ];
}

function formatEuroForPdf(float $amount): string
{
    return '€ '.number_format($amount, 2, ',', '.');
}

function renderInvoicePdfHtmlForInvoice(Invoice $invoice): string
{
    $invoice->load([
        'order.customer',
        'order.items.product',
        'order.delivery.items',
    ]);

    $calculator = app(InvoiceLineCalculator::class);
    $order = $invoice->order;
    $delivery = $order->delivery;
    $lines = $calculator->lines($order, $delivery);
    $totals = $calculator->totals($order, $delivery);

    return view('pdf.invoice', [
        'invoice' => $invoice,
        'lines' => $lines,
        'vatByRate' => $totals['vat_by_rate'],
    ])->render();
}

/**
 * Assert calculator, invoice record, PDF HTML and UBL all report identical amounts.
 */
function assertInvoiceConsistencyAcrossFormats(Order $order, Delivery $delivery, Invoice $invoice): void
{
    $calculator = app(InvoiceLineCalculator::class);
    $totals = $calculator->totals($order, $delivery);
    $service = app(InvoiceService::class);

    expect((float) $invoice->subtotal_amount)->toBe($totals['subtotal'])
        ->and((float) $invoice->vat_amount)->toBe($totals['vat'])
        ->and((float) $invoice->total_amount)->toBe($totals['total']);

    $service->generatePdf($invoice);
    $service->generateUbl($invoice->refresh());
    $invoice->refresh();

    Storage::disk(UploadStorage::diskName())->assertExists($invoice->pdf_path);
    Storage::disk(UploadStorage::diskName())->assertExists($invoice->ubl_path);

    $html = renderInvoicePdfHtmlForInvoice($invoice);
    $ubl = Storage::disk(UploadStorage::diskName())->get($invoice->ubl_path);
    $doc = ublDocument($ubl);

    expect($html)->toContain(formatEuroForPdf($totals['subtotal']))
        ->and($html)->toContain(formatEuroForPdf($totals['total']));

    foreach ($totals['vat_by_rate'] as $group) {
        $vatLabel = $group['rate'] == 0.0
            ? 'BTW (0% — vrijgesteld)'
            : 'BTW ('.number_format($group['rate'], 0, ',', '.').'%)';

        expect($html)->toContain($vatLabel)
            ->and($html)->toContain(formatEuroForPdf($group['vat_amount']));
    }

    expect(sumUblLineTotals($ubl))->toBe($totals['subtotal'])
        ->and((float) $doc->xpath('//cac:TaxTotal/cbc:TaxAmount')[0])->toBe($totals['vat'])
        ->and((float) $doc->xpath('//cac:LegalMonetaryTotal/cbc:TaxExclusiveAmount')[0])->toBe($totals['subtotal'])
        ->and((float) $doc->xpath('//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount')[0])->toBe($totals['total'])
        ->and((float) $doc->xpath('//cac:LegalMonetaryTotal/cbc:PayableAmount')[0])->toBe($totals['total']);

    $ublSubtotals = $doc->xpath('//cac:TaxTotal/cac:TaxSubtotal');
    expect($ublSubtotals)->toHaveCount(count($totals['vat_by_rate']));

    foreach ($totals['vat_by_rate'] as $group) {
        $match = collect($ublSubtotals)->first(
            fn (SimpleXMLElement $node): bool => (float) $node->xpath('cac:TaxCategory/cbc:Percent')[0] === $group['rate']
        );

        expect($match)->not->toBeNull()
            ->and((float) $match->xpath('cbc:TaxableAmount')[0])->toBe($group['taxable_amount'])
            ->and((float) $match->xpath('cbc:TaxAmount')[0])->toBe($group['vat_amount']);
    }
}
