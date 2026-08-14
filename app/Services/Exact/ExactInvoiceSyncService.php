<?php

namespace App\Services\Exact;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceLineCalculator;
use Picqer\Financials\Exact\ApiException;
use Picqer\Financials\Exact\Connection;
use Picqer\Financials\Exact\PrintedSalesInvoice;
use Picqer\Financials\Exact\SalesInvoice;

class ExactInvoiceSyncService
{
    public function __construct(
        private ExactOnlineClient $client,
        private ExactCustomerSyncService $customerSyncService,
        private ExactItemSyncService $itemSyncService,
    ) {}

    public function push(Invoice $invoice): ExactInvoicePushResult
    {
        return $this->client->call(function (Connection $connection) use ($invoice): ExactInvoicePushResult {
            $invoice->loadMissing([
                'order.customer',
                'order.items.product',
                'order.delivery.items',
            ]);

            $order = $invoice->order;
            $customer = $order->customer;

            $this->ensureCustomerIsSynced($customer);

            $itemIdsByProductId = $this->ensureProductsAreSynced($order);

            $payload = ExactInvoiceMapper::toExactSalesInvoice(
                $invoice,
                $itemIdsByProductId,
                $this->crateDepositItemId($order),
            );

            $salesInvoice = new SalesInvoice($connection);

            foreach ($payload as $field => $value) {
                $salesInvoice->{$field} = $value;
            }

            try {
                $salesInvoice->save();
            } catch (ApiException $exception) {
                throw ExactApiException::fromPicqer($exception);
            }

            if (! filled($salesInvoice->InvoiceID)) {
                throw new ExactApiException('Exact heeft geen factuur-ID teruggegeven na aanmaken.');
            }

            $invoiceId = (string) $salesInvoice->InvoiceID;
            $documentNumber = filled($salesInvoice->InvoiceNumber)
                ? (string) $salesInvoice->InvoiceNumber
                : null;

            if (blank($documentNumber) && config('exact.invoice.print_on_push', true)) {
                $this->printInvoice($connection, $invoiceId);
                $salesInvoice = $this->findSalesInvoice($connection, $invoiceId);
                $documentNumber = filled($salesInvoice?->InvoiceNumber)
                    ? (string) $salesInvoice->InvoiceNumber
                    : null;
            }

            return new ExactInvoicePushResult(
                invoiceId: $invoiceId,
                documentNumber: $documentNumber,
            );
        });
    }

    private function ensureCustomerIsSynced(Customer $customer): void
    {
        if (filled($customer->exact_account_id)) {
            return;
        }

        $accountId = $this->customerSyncService->sync($customer);

        $customer->updateQuietly([
            'exact_account_id' => $accountId,
            'exact_synced_at' => now(),
            'exact_sync_error' => null,
        ]);

        $customer->refresh();
    }

    /**
     * Statiegeld is geen product uit onze catalogus, maar een vast artikel in
     * Exact. We zoeken het ID op bij de ingestelde artikelcode.
     */
    private function crateDepositItemId($order): ?string
    {
        $hasCrateDeposit = app(InvoiceLineCalculator::class)
            ->crateDepositLines($order, $order->delivery)
            ->isNotEmpty();

        if (! $hasCrateDeposit) {
            return null;
        }

        return $this->itemSyncService->ensureCrateDepositItem();
    }

    /**
     * @return array<int, string>
     */
    private function ensureProductsAreSynced($order): array
    {
        $lines = app(InvoiceLineCalculator::class)->lines($order, $order->delivery);
        $billableProductIds = $lines
            ->filter(fn (array $line): bool => $line['delivered_kg'] > 0 && $line['line_subtotal'] != 0.0)
            ->map(fn (array $line): ?int => $order->items->firstWhere('id', $line['order_item_id'])?->product_id)
            ->filter()
            ->unique()
            ->values();

        $itemIdsByProductId = [];

        foreach ($billableProductIds as $productId) {
            /** @var Product|null $product */
            $product = $order->items->firstWhere('product_id', $productId)?->product;

            if ($product === null) {
                continue;
            }

            /**
             * Een product dat sinds de laatste sync niet gewijzigd is, hoeft niet
             * opnieuw naar Exact: dat scheelt twee API-calls per product en maakt
             * het boeken van een factuur merkbaar sneller.
             */
            if ($this->isSyncedWithExact($product)) {
                $itemIdsByProductId[$product->id] = (string) $product->exact_item_id;

                continue;
            }

            $itemId = $this->itemSyncService->sync($product);

            $product->updateQuietly([
                'exact_item_id' => $itemId,
                'exact_article_code' => $product->exact_article_code ?: ExactItemMapper::articleCode($product),
                'exact_synced_at' => now(),
                'exact_sync_error' => null,
            ]);

            $itemIdsByProductId[$product->id] = $itemId;
        }

        return $itemIdsByProductId;
    }

    private function isSyncedWithExact(Product $product): bool
    {
        return filled($product->exact_item_id)
            && $product->exact_synced_at !== null
            && $product->exact_synced_at->gte($product->updated_at);
    }

    private function printInvoice(Connection $connection, string $invoiceId): void
    {
        $printedInvoice = new PrintedSalesInvoice($connection);
        $printedInvoice->InvoiceID = $invoiceId;
        $printedInvoice->SendEmailToCustomer = false;
        $printedInvoice->SendInvoiceToCustomerPostbox = false;
        $printedInvoice->SendInvoiceViaPeppol = false;
        $printedInvoice->SendOutputBasedOnAccount = false;

        try {
            $printedInvoice->save();
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }

        if (filled($printedInvoice->DocumentCreationError)) {
            throw new ExactApiException((string) $printedInvoice->DocumentCreationError);
        }
    }

    private function findSalesInvoice(Connection $connection, string $invoiceId): ?SalesInvoice
    {
        $salesInvoice = new SalesInvoice($connection);

        try {
            return $salesInvoice->find($invoiceId);
        } catch (ApiException $exception) {
            throw ExactApiException::fromPicqer($exception);
        }
    }
}
