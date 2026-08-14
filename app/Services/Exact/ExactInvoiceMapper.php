<?php

namespace App\Services\Exact;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InvoiceLineCalculator;
use Illuminate\Support\Collection;

class ExactInvoiceMapper
{
    private const TYPE_PRODUCT_FALLBACK = InvoiceLineCalculator::TYPE_PRODUCT;

    /**
     * @return array{
     *     OrderedBy: string,
     *     InvoiceTo: string,
     *     DeliverTo: string,
     *     Journal: string,
     *     Description: string,
     *     YourRef: string,
     *     InvoiceDate: string,
     *     DueDate: string,
     *     SalesInvoiceLines: array<int, array<string, mixed>>,
     * }
     */
    public static function toExactSalesInvoice(
        Invoice $invoice,
        array $itemIdsByProductId,
        ?string $crateDepositItemId = null,
    ): array {
        $invoice->loadMissing([
            'order.customer',
            'order.items.product',
            'order.delivery.items',
        ]);

        $order = $invoice->order;
        $customer = $order->customer;
        $lines = app(InvoiceLineCalculator::class)->lines($order, $order->delivery);

        $salesLines = $lines
            ->filter(fn (array $line): bool => self::isBillableLine($line))
            ->map(function (array $line) use ($order, $itemIdsByProductId, $crateDepositItemId): array {
                if (($line['type'] ?? InvoiceLineCalculator::TYPE_PRODUCT) === InvoiceLineCalculator::TYPE_CRATE_DEPOSIT) {
                    return self::crateDepositLine($line, $crateDepositItemId);
                }

                /** @var OrderItem $orderItem */
                $orderItem = $order->items->firstWhere('id', $line['order_item_id']);
                $product = $orderItem?->product;

                if ($product === null) {
                    throw new ExactApiException('Orderregel heeft geen gekoppeld product voor Exact-boeking.');
                }

                $itemId = $itemIdsByProductId[$product->id] ?? null;

                if (! filled($itemId)) {
                    throw new ExactApiException(sprintf(
                        'Product "%s" heeft geen Exact-artikel-ID.',
                        $product->name,
                    ));
                }

                $payload = [
                    'Item' => $itemId,
                    'ItemCode' => ExactItemMapper::articleCode($product),
                    'Description' => self::lineDescription($line),
                    'Quantity' => $line['delivered_kg'],
                    'UnitPrice' => $line['price_per_kg'],
                    'UnitCode' => (string) config('exact.item.unit', 'kg'),
                ];

                if (filled($vatCode = self::vatCodeForRate($line['vat_rate']))) {
                    $payload['VATCode'] = $vatCode;
                }

                return $payload;
            })
            ->values()
            ->all();

        if ($salesLines === []) {
            throw new ExactApiException('De factuur heeft geen te factureren regels voor Exact.');
        }

        return [
            'OrderedBy' => (string) $customer->exact_account_id,
            'InvoiceTo' => (string) $customer->exact_account_id,
            'DeliverTo' => (string) $customer->exact_account_id,
            'Journal' => (string) config('exact.invoice.journal'),
            'Description' => sprintf('Order %s', $order->order_number),
            'YourRef' => $invoice->invoice_number,
            'InvoiceDate' => $invoice->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'DueDate' => $invoice->due_date?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d'),
            'SalesInvoiceLines' => $salesLines,
        ];
    }

    /**
     * Productregels tellen alleen mee als er kilo's geleverd zijn; statiegeld
     * heeft geen gewicht en telt mee zodra er een bedrag op staat.
     *
     * @param  array<string, mixed>  $line
     */
    private static function isBillableLine(array $line): bool
    {
        if (($line['type'] ?? self::TYPE_PRODUCT_FALLBACK) === InvoiceLineCalculator::TYPE_CRATE_DEPOSIT) {
            return $line['line_subtotal'] != 0.0;
        }

        return $line['delivered_kg'] > 0 && $line['line_subtotal'] != 0.0;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private static function crateDepositLine(array $line, ?string $crateDepositItemId): array
    {
        if (! filled($crateDepositItemId)) {
            throw new ExactApiException(
                'Er is geen Exact-artikel voor statiegeld kratten ingesteld (EXACT_CRATE_DEPOSIT_ARTICLE_CODE).',
            );
        }

        $payload = [
            'Item' => $crateDepositItemId,
            'ItemCode' => $line['article_code'],
            'Description' => $line['product_name'],
            'Quantity' => $line['billing_quantity'],
            'UnitPrice' => $line['billing_unit_price'],
            'UnitCode' => $line['billing_unit_code'],
        ];

        if (filled($vatCode = config('exact.crate_deposit.vat_code'))) {
            $payload['VATCode'] = (string) $vatCode;
        }

        return $payload;
    }

    /**
     * @param  array{product_name: string, unit: string, delivered_kg: float}  $line
     */
    public static function lineDescription(array $line): string
    {
        return sprintf(
            '%s (%s, %.3f kg)',
            $line['product_name'],
            $line['unit'],
            $line['delivered_kg'],
        );
    }

    /**
     * De btw-tarieven staan als sleutel met een punt in de config ('21.00').
     * `config()` splitst op punten, dus die sleutel moet uit de array zelf
     * komen — anders wordt er nooit een btw-code meegestuurd naar Exact.
     */
    public static function vatCodeForRate(float $vatRate): ?string
    {
        $rateKey = number_format($vatRate, 2, '.', '');

        /** @var array<string, string|null> $vatCodes */
        $vatCodes = config('exact.invoice.vat_codes', []);

        return $vatCodes[$rateKey] ?? null;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, string>
     */
    public static function requiredProductIds(Collection $products): array
    {
        return $products
            ->filter()
            ->unique('id')
            ->mapWithKeys(fn (Product $product): array => [$product->id => $product->id])
            ->all();
    }
}
