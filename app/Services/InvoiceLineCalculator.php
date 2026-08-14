<?php

namespace App\Services;

use App\Models\CrateTransaction;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Single source of truth for invoice line amounts and totals.
 *
 * The invoice record, the PDF and the UBL export must all use this calculator
 * so they can never produce diverging amounts. Billing is based on delivered
 * weight in kg (delivered quantity × snapshot box weight), the snapshot price
 * per kg on the order item, and the per-line VAT rate snapshot.
 */
class InvoiceLineCalculator
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_CRATE_DEPOSIT = 'crate_deposit';

    /**
     * Build the billable invoice lines for an order.
     *
     * @return Collection<int, array{
     *     order_item_id: int,
     *     product_name: string,
     *     unit: string,
     *     ordered_quantity: float,
     *     delivered_quantity: float,
     *     box_weight_kg: float,
     *     delivered_kg: float,
     *     price_per_kg: float,
     *     unit_price: float,
     *     line_subtotal: float,
     *     vat_rate: float,
     *     line_vat: float,
     *     missed_reason: ?string,
     *     is_missed: bool,
     * }>
     */
    public function lines(Order $order, ?Delivery $delivery): Collection
    {
        return $this->productLines($order, $delivery)
            ->concat($this->crateDepositLines($order, $delivery))
            ->values();
    }

    /**
     * Statiegeldregels: het meegegeven aantal kratten wordt in rekening gebracht
     * en het direct retour ontvangen aantal wordt op dezelfde factuur verrekend.
     * Statiegeld is btw-vrijgesteld.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function crateDepositLines(Order $order, ?Delivery $delivery): Collection
    {
        if ($delivery === null) {
            return collect();
        }

        $depositPrice = $order->customer->crateDepositPrice();

        if ($depositPrice <= 0.0) {
            return collect();
        }

        $crates = $this->crateTotals($order);
        $lines = collect();

        if ($crates['given'] > 0) {
            $lines->push($this->crateLine(
                description: 'Statiegeld kratten',
                crates: $crates['given'],
                depositPrice: $depositPrice,
            ));
        }

        if ($crates['returned'] > 0) {
            $lines->push($this->crateLine(
                description: 'Statiegeld kratten retour',
                crates: -$crates['returned'],
                depositPrice: $depositPrice,
            ));
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function crateLine(string $description, int $crates, float $depositPrice): array
    {
        $lineSubtotal = round($crates * $depositPrice, 2);

        return [
            'order_item_id' => null,
            'type' => self::TYPE_CRATE_DEPOSIT,
            'article_code' => (string) config('exact.crate_deposit.article_code', ''),
            'product_name' => $description,
            'unit' => 'krat',
            'ordered_quantity' => (float) $crates,
            'delivered_quantity' => (float) $crates,
            'box_weight_kg' => 0.0,
            'delivered_kg' => 0.0,
            'price_per_kg' => 0.0,
            'unit_price' => $depositPrice,
            'billing_quantity' => (float) $crates,
            'billing_unit_price' => $depositPrice,
            'billing_unit_code' => (string) config('exact.crate_deposit.unit', 'st'),
            'line_subtotal' => $lineSubtotal,
            'vat_rate' => 0.0,
            'line_vat' => 0.0,
            'missed_reason' => null,
            'is_missed' => false,
        ];
    }

    /**
     * @return array{given: int, returned: int}
     */
    private function crateTotals(Order $order): array
    {
        $order->loadMissing('routeStops');
        $routeId = $order->routeStops->first()?->route_id;

        $transaction = CrateTransaction::query()
            ->where('customer_id', $order->customer_id)
            ->when($routeId !== null, fn ($query) => $query->where('route_id', $routeId))
            ->latest()
            ->first();

        return [
            'given' => (int) ($transaction->crates_given ?? 0),
            'returned' => (int) ($transaction->crates_returned ?? 0),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function productLines(Order $order, ?Delivery $delivery): Collection
    {
        return $order->items->map(function (OrderItem $item) use ($order, $delivery): array {
            $deliveryItem = $delivery?->items->firstWhere('order_item_id', $item->id);
            $deliveredQuantity = (float) ($deliveryItem?->delivered_quantity ?? 0);
            $boxWeightKg = (float) $item->box_weight_kg;
            $deliveredKg = round($deliveredQuantity * $boxWeightKg, 3);
            $pricePerKg = (float) $item->price_per_kg;
            $lineSubtotal = round($deliveredKg * $pricePerKg, 2);
            $vatRate = $this->lineVatRate($order, $item);
            $missedReason = $deliveryItem?->missed_reason;

            return [
                'order_item_id' => $item->id,
                'type' => self::TYPE_PRODUCT,
                'article_code' => $item->product?->exact_article_code ?? '',
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'ordered_quantity' => (float) $item->quantity,
                'delivered_quantity' => $deliveredQuantity,
                'box_weight_kg' => $boxWeightKg,
                'delivered_kg' => $deliveredKg,
                'price_per_kg' => $pricePerKg,
                'unit_price' => (float) $item->unit_price,
                'billing_quantity' => $deliveredKg,
                'billing_unit_price' => $pricePerKg,
                'billing_unit_code' => (string) config('exact.item.unit', 'kg'),
                'line_subtotal' => $lineSubtotal,
                'vat_rate' => $vatRate,
                'line_vat' => round($lineSubtotal * $vatRate / 100, 2),
                'missed_reason' => $missedReason,
                'is_missed' => $deliveredQuantity == 0.0 && $missedReason !== null,
            ];
        })->values();
    }

    /**
     * Calculate the invoice totals from the same lines used everywhere else.
     *
     * @return array{
     *     subtotal: float,
     *     vat: float,
     *     total: float,
     *     vat_by_rate: array<int, array{rate: float, taxable_amount: float, vat_amount: float}>,
     * }
     */
    public function totals(Order $order, ?Delivery $delivery): array
    {
        $lines = $this->lines($order, $delivery);
        $subtotal = round((float) $lines->sum('line_subtotal'), 2);
        $vatByRate = $this->vatByRate($lines);
        $vat = round((float) collect($vatByRate)->sum('vat_amount'), 2);

        return [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => round($subtotal + $vat, 2),
            'vat_by_rate' => $vatByRate,
        ];
    }

    /**
     * Group VAT amounts by rate (0%, 9%, 21%, etc.).
     *
     * @param  Collection<int, array{line_subtotal: float, line_vat: float, vat_rate: float}>  $lines
     * @return array<int, array{rate: float, taxable_amount: float, vat_amount: float}>
     */
    public function vatByRate(Collection $lines): array
    {
        return $lines
            ->groupBy(fn (array $line): string => number_format($line['vat_rate'], 2, '.', ''))
            ->map(function (Collection $group, string $rateKey): array {
                return [
                    'rate' => (float) $rateKey,
                    'taxable_amount' => round((float) $group->sum('line_subtotal'), 2),
                    'vat_amount' => round((float) $group->sum('line_vat'), 2),
                ];
            })
            ->sortBy('rate')
            ->values()
            ->filter(fn (array $group): bool => $group['taxable_amount'] != 0.0 || $group['vat_amount'] != 0.0)
            ->values()
            ->all();
    }

    public function vatRate(Order $order): float
    {
        return $order->customer->is_vat_exempt ? 0.0 : 21.0;
    }

    protected function lineVatRate(Order $order, OrderItem $item): float
    {
        if ($order->customer->is_vat_exempt) {
            return 0.0;
        }

        return (float) ($item->vat_rate ?? 21.0);
    }
}
