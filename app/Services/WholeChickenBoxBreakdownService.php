<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\ProductGramVariant;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Verwerkt wat de chauffeur per doos heeft geladen. Dozen met dezelfde
 * gramvariant worden samengevoegd; wijkt een deel van de dozen af, dan wordt de
 * orderregel gesplitst zodat elke regel één geladen variant houdt en factuur,
 * leverbon en Exact per regel blijven kloppen.
 */
class WholeChickenBoxBreakdownService
{
    public function __construct(
        protected OrderItemLoadingService $loadingService,
    ) {}

    /**
     * @param  array<int, array{quantity: float, variant: ProductGramVariant, weight_kg: ?float}>  $rows
     * @return EloquentCollection<int, OrderItem>
     */
    public function apply(OrderItem $orderItem, array $rows, ?string $substitutionReason = null): EloquentCollection
    {
        $groups = $this->groupRows($rows);

        if ($groups->isEmpty()) {
            throw new InvalidArgumentException('Vul minstens één doos in.');
        }

        // Eerdere splitsingen horen bij dezelfde bestelde regel; hun dozen tellen
        // mee, anders zou een tweede correctie dozen laten verdwijnen.
        $orderedQuantity = (float) $orderItem->quantity + (float) $this->splitsOf($orderItem)->sum('quantity');
        $assignedQuantity = (float) $groups->sum('quantity');
        $shortage = round($orderedQuantity - $assignedQuantity, 2);

        return DB::transaction(function () use ($orderItem, $groups, $shortage, $substitutionReason): EloquentCollection {
            $this->removeEarlierSplits($orderItem);

            $items = new EloquentCollection;

            foreach ($groups->values() as $index => $group) {
                $isFirst = $index === 0;

                // De eerste regel draagt het verschil tussen besteld en geladen,
                // zodat het bestelde aantal van de order intact blijft.
                $orderedForGroup = $isFirst
                    ? max(0.0, round($group['quantity'] + $shortage, 2))
                    : $group['quantity'];

                $item = $isFirst
                    ? $orderItem
                    : $this->replicateOrderItem($orderItem);

                $this->applyOrderedQuantity($item, $orderedForGroup);

                $this->loadingService->recordLoadedVariant($item, $group['variant'], $substitutionReason);
                $this->loadingService->recordLoadedQuantity($item->fresh(), $group['quantity']);

                $fresh = $item->fresh();
                $this->loadingService->recordActualWeight($fresh, $group['weight_kg']);

                $items->push($fresh->fresh());
            }

            return $items;
        });
    }

    /**
     * Dozen met dezelfde variant worden één regel. Gewichten tellen op; is niet
     * bij elke doos een gewicht ingevuld, dan geldt het doosgewicht van de variant.
     *
     * @param  array<int, array{quantity: float, variant: ProductGramVariant, weight_kg: ?float}>  $rows
     * @return Collection<int, array{quantity: float, variant: ProductGramVariant, weight_kg: ?float}>
     */
    private function groupRows(array $rows): Collection
    {
        $groups = collect();

        foreach ($rows as $row) {
            $quantity = round((float) $row['quantity'], 2);

            if ($quantity <= 0) {
                continue;
            }

            $variant = $row['variant'];
            $weight = $row['weight_kg'] !== null && $row['weight_kg'] > 0 ? (float) $row['weight_kg'] : null;
            $key = $variant->id;

            if (! $groups->has($key)) {
                $groups[$key] = [
                    'quantity' => 0.0,
                    'variant' => $variant,
                    'weight_kg' => null,
                    'weighed_quantity' => 0.0,
                ];
            }

            $group = $groups[$key];
            $group['quantity'] = round($group['quantity'] + $quantity, 2);

            if ($weight !== null) {
                $group['weight_kg'] = round((float) $group['weight_kg'] + $weight, 3);
                $group['weighed_quantity'] = round($group['weighed_quantity'] + $quantity, 2);
            }

            $groups[$key] = $group;
        }

        return $groups->map(function (array $group): array {
            // Een gewicht telt alleen als elke doos van deze variant gewogen is;
            // anders zou een deelgewicht als totaalgewicht gefactureerd worden.
            if ($group['weight_kg'] !== null && abs($group['weighed_quantity'] - $group['quantity']) > 0.001) {
                $group['weight_kg'] = null;
            }

            unset($group['weighed_quantity']);

            return $group;
        })->values();
    }

    /**
     * Eerder gesplitste regels van dezelfde bestelling worden opgeruimd, zodat
     * een tweede correctie geen dubbele regels achterlaat.
     */
    private function removeEarlierSplits(OrderItem $orderItem): void
    {
        $this->splitsOf($orderItem)->each(fn (OrderItem $item) => $item->delete());
    }

    /**
     * @return EloquentCollection<int, OrderItem>
     */
    public function splitsOf(OrderItem $orderItem): EloquentCollection
    {
        return OrderItem::query()
            ->where('split_from_order_item_id', $orderItem->id)
            ->orderBy('id')
            ->get();
    }

    private function replicateOrderItem(OrderItem $orderItem): OrderItem
    {
        $copy = $orderItem->replicate();
        $copy->split_from_order_item_id = $orderItem->id;
        $copy->loaded_gram_variant_id = null;
        $copy->substituted_from_gram_variant_id = null;
        $copy->loaded_weight_grams = null;
        $copy->loaded_pieces_per_box = null;
        $copy->loaded_box_weight_kg = null;
        $copy->loaded_packaging_quantity = null;
        $copy->loaded_total_weight_kg = null;
        $copy->loaded_pieces = null;
        $copy->loaded_actual_weight_kg = null;
        $copy->loading_substitution_reason = null;
        $copy->loaded_at = null;
        $copy->save();

        return $copy;
    }

    /**
     * Zet het bestelde aantal van de (deel)regel en schaalt de bestelde stuks,
     * kilo's en het subtotaal mee.
     */
    private function applyOrderedQuantity(OrderItem $item, float $quantity): void
    {
        $piecesPerBox = (float) ($item->pieces_per_box ?? 0);
        $boxWeightKg = (float) $item->box_weight_kg;
        $unitPrice = (float) $item->unit_price;

        $item->update([
            'quantity' => $quantity,
            'ordered_pieces' => round($piecesPerBox * $quantity, 2),
            'ordered_total_weight_kg' => round($boxWeightKg * $quantity, 3),
            'subtotal' => round($unitPrice * $quantity, 2),
        ]);
    }
}
