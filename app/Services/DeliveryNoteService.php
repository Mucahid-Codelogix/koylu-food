<?php

namespace App\Services;

use App\Models\CrateTransaction;
use App\Models\Delivery;
use App\Models\Order;
use App\Support\UploadStorage;

class DeliveryNoteService
{
    /**
     * Ken een uniek leverbonnummer toe zodra een order wordt ingepland.
     * De concept-leverbon en de definitieve leverbon delen dit nummer.
     */
    public function assignNumber(Order $order): Order
    {
        if (filled($order->delivery_note_number)) {
            return $order;
        }

        $order->update(['delivery_note_number' => $this->generateNumber()]);

        return $order;
    }

    /**
     * Bouw alle gegevens voor de leverbon-PDF. Zonder levering krijg je de
     * werkbon (concept) met lege krat- en handtekeningvakken; met levering de
     * definitieve versie.
     *
     * @return array{
     *     order: Order,
     *     delivery: ?Delivery,
     *     isFinal: bool,
     *     lines: array<int, array<string, mixed>>,
     *     totals: array{quantity: float, delivered_quantity: float, weight_kg: float},
     *     crates: array{given: int, returned: int},
     *     signatures: array{receiver: ?string, driver: ?string},
     * }
     */
    public function viewData(Order $order, ?Delivery $delivery = null): array
    {
        $order->loadMissing([
            'customer',
            'items',
            'routeStops.route.driver',
        ]);

        $delivery?->loadMissing('items');

        $isFinal = $delivery !== null;
        $lines = [];
        $totalQuantity = 0.0;
        $totalDeliveredQuantity = 0.0;
        $totalWeightKg = 0.0;

        foreach ($order->items as $item) {
            $boxWeightKg = (float) $item->box_weight_kg;
            $orderedQuantity = (float) $item->quantity;
            $plannedQuantity = (float) ($item->loaded_packaging_quantity ?? $item->quantity);

            $deliveryItem = $delivery?->items->firstWhere('order_item_id', $item->id);
            $deliveredQuantity = (float) ($deliveryItem->delivered_quantity ?? 0);
            $missedReason = $deliveryItem->missed_reason ?? null;

            $quantityForWeight = $isFinal ? $deliveredQuantity : $plannedQuantity;

            $lines[] = [
                'product_name' => $item->product_name,
                'supplier_name' => $item->supplier_name,
                'unit' => $item->unit,
                'quantity' => $this->formatQuantity($plannedQuantity),
                'ordered_quantity' => $this->formatQuantity($orderedQuantity),
                'delivered_quantity' => $this->formatQuantity($deliveredQuantity),
                'weight_kg' => round($quantityForWeight * $boxWeightKg, 2),
                'missed_reason' => $missedReason,
                'is_missed' => $deliveredQuantity == 0.0 && filled($missedReason),
                'has_deviation' => abs($deliveredQuantity - $orderedQuantity) > 0.001,
                'deviation' => $this->formatQuantity($orderedQuantity - $deliveredQuantity),
                'return_note' => $deliveryItem->return_note ?? null,
            ];

            $totalQuantity += $plannedQuantity;
            $totalDeliveredQuantity += $deliveredQuantity;
            $totalWeightKg += $quantityForWeight * $boxWeightKg;
        }

        return [
            'order' => $order,
            'delivery' => $delivery,
            'isFinal' => $isFinal,
            'lines' => $lines,
            'totals' => [
                'quantity' => $this->formatQuantity($totalQuantity),
                'delivered_quantity' => $this->formatQuantity($totalDeliveredQuantity),
                'weight_kg' => round($totalWeightKg, 2),
            ],
            'crates' => $this->crateTotals($order),
            'signatures' => [
                'receiver' => $this->signatureDataUri($delivery?->signature_path),
                'driver' => $this->signatureDataUri($delivery?->driver_signature_path),
            ],
        ];
    }

    public function filename(Order $order, bool $isFinal): string
    {
        return sprintf(
            'leverbon-%s%s.pdf',
            $order->delivery_note_number ?? $order->order_number,
            $isFinal ? '' : '-werkbon',
        );
    }

    /**
     * @return array{given: int, returned: int}
     */
    private function crateTotals(Order $order): array
    {
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
     * DomPDF haalt geen externe bestanden op, dus de handtekening gaat als
     * data-URI mee. Werkt daarmee ook als uploads op Spaces staan.
     *
     * Is de handtekening niet te lezen — bestand weg of opslag onbereikbaar —
     * dan komt de leverbon eruit zonder handtekening in plaats van dat de
     * download stukloopt.
     */
    private function signatureDataUri(?string $path): ?string
    {
        $contents = UploadStorage::read($path);

        return $contents === null
            ? null
            : 'data:image/png;base64,'.base64_encode($contents);
    }

    private function formatQuantity(float $quantity): float
    {
        return round($quantity, 2);
    }

    public function generateNumber(): string
    {
        $year = now()->year;
        $prefix = "LB{$year}-";

        $lastNumber = Order::query()
            ->where('delivery_note_number', 'like', $prefix.'%')
            ->orderByDesc('delivery_note_number')
            ->value('delivery_note_number');

        $sequence = $lastNumber !== null
            ? ((int) substr($lastNumber, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
