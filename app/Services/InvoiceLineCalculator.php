<?php

namespace App\Services;

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
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'ordered_quantity' => (float) $item->quantity,
                'delivered_quantity' => $deliveredQuantity,
                'box_weight_kg' => $boxWeightKg,
                'delivered_kg' => $deliveredKg,
                'price_per_kg' => $pricePerKg,
                'unit_price' => (float) $item->unit_price,
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
