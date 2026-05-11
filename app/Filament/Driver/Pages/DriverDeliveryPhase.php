<?php

namespace App\Filament\Driver\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Models\CrateTransaction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Services\InvoiceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class DriverDeliveryPhase extends Page
{
    protected string $view = 'filament.driver.pages.driver-delivery-phase';

    protected static ?string $title = 'Levering';

    protected static bool $shouldRegisterNavigation = false;

    public Route $route;

    public int $currentStopIndex = 0;

    public array $deliveryData = [];

    public string $signature = '';

    public string $receiverName = '';

    public int $cratesGiven = 0;

    public int $cratesReturned = 0;

    public function mount(): void
    {
        $routeId = request()->query('routeId') ?? request()->route('routeId');

        $this->route = Route::with([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
        ])->findOrFail($routeId);

        abort_unless($this->route->driver_id === auth()->id(), 403);

        // Haal fresh stops op direct uit DB
        $firstPendingIndex = $this->route->routeStops
            ->search(fn ($stop) => $stop->status !== RouteStopStatus::DELIVERED);

        $this->currentStopIndex = $firstPendingIndex !== false ? $firstPendingIndex : 0;

        $this->initDeliveryData();
    }

    public function getCurrentStop(): RouteStop
    {
        return $this->route->routeStops[$this->currentStopIndex];
    }

    public function getTotalStops(): int
    {
        return $this->route->routeStops->count();
    }

    private function initDeliveryData(): void
    {
        $stop = $this->getCurrentStop();
        $this->signature = '';
        $this->receiverName = '';
        $this->cratesGiven = 0;
        $this->cratesReturned = 0;
        $this->deliveryData = [];

        $this->dispatch('clear-signature');

        // Bestaande delivery ophalen indien aanwezig
        $existingDelivery = Delivery::with('items')
            ->where('order_id', $stop->order_id)
            ->first();

        foreach ($stop->order->items as $item) {
            $existingItem = $existingDelivery?->items
                ->firstWhere('order_item_id', $item->id);

            $this->deliveryData[$item->id] = [
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'ordered_quantity' => $item->quantity,
                'delivered_quantity' => $existingItem?->delivered_quantity ?? $item->quantity,
                'is_missed' => $existingItem ? $existingItem->delivered_quantity == 0 : false,
                'missed_reason' => $existingItem?->missed_reason ?? '',
            ];
        }

        // Bestaande kratten ophalen
        $existingCrates = CrateTransaction::where('route_id', $this->route->id)
            ->where('customer_id', $stop->order->customer_id)
            ->latest()
            ->first();

        if ($existingCrates) {
            $this->cratesGiven = $existingCrates->crates_given;
            $this->cratesReturned = $existingCrates->crates_returned;
        }

        // Bestaande handtekening naam ophalen
        if ($existingDelivery) {
            $this->receiverName = $existingDelivery->receiver_name ?? '';
        }
    }

    public function saveDelivery(): void
    {
        $stop = $this->getCurrentStop();

        // US-C7: Handtekening verplicht
        if (empty($this->signature)) {
            Notification::make()
                ->title('Handtekening ontbreekt')
                ->warning()
                ->send();

            return;
        }

        // Sla handtekening op als bestand
        $signaturePath = $this->saveSignature($this->signature, $stop->id);

        // Maak delivery aan
        $delivery = Delivery::updateOrCreate(
            ['order_id' => $stop->order_id],
            [
                'delivered_at' => now(),
                'receiver_name' => $this->receiverName,
                'signature_path' => $signaturePath,
                'status' => DeliveryStatus::DELIVERED,
            ]
        );

        // US-C4 & C5: Sla delivery items op
        foreach ($this->deliveryData as $orderItemId => $data) {
            DeliveryItem::updateOrCreate(
                [
                    'delivery_id' => $delivery->id,
                    'order_item_id' => $orderItemId,
                ],
                [
                    'product_id' => $stop->order->items->find($orderItemId)->product_id,
                    'ordered_quantity' => $data['ordered_quantity'],
                    'delivered_quantity' => $data['is_missed'] ? 0 : $data['delivered_quantity'],
                    'missed_reason' => $data['is_missed'] ? $data['missed_reason'] : null,
                ]
            );
        }

        // US-C6: Kratten registreren
        if ($this->cratesGiven > 0 || $this->cratesReturned > 0) {
            CrateTransaction::create([
                'customer_id' => $stop->order->customer_id,
                'route_id' => $this->route->id,
                'crates_given' => $this->cratesGiven,
                'crates_returned' => $this->cratesReturned,
            ]);
        }

        // Update stop status
        $stop->update(['status' => RouteStopStatus::DELIVERED]);
        $stop->order->update(['status' => OrderStatus::DELIVERED]);

        app(InvoiceService::class)->createFromDelivery($delivery);

        Notification::make()
            ->title('Levering opgeslagen!')
            ->success()
            ->send();

        // US-C8: Naar volgende stop of afronden
        $this->nextStop();
    }

    public function nextStop(): void
    {
        if ($this->currentStopIndex < $this->getTotalStops() - 1) {
            $this->currentStopIndex++;
            $this->deliveryData = [];
            $this->receiverName = '';
            $this->route->load([
                'routeStops' => fn ($q) => $q->orderBy('stop_order'),
                'routeStops.order.customer',
                'routeStops.order.items.product',
            ]);
            $this->initDeliveryData();
            $this->dispatch('stop-changed');
        } else {
            $this->route->update([
                'status' => RouteStatus::COMPLETED,
                'completed_at' => now(),
            ]);
            $this->redirect(DriverDashboard::getUrl());
        }
    }

    public function skipStop(): void
    {
        if ($this->currentStopIndex < $this->getTotalStops() - 1) {
            $this->currentStopIndex++;
            $this->deliveryData = []; // ← eerst leegmaken
            $this->route->load([
                'routeStops' => fn ($q) => $q->orderBy('stop_order'),
                'routeStops.order.customer',
                'routeStops.order.items.product',
            ]);
            $this->initDeliveryData();
            $this->dispatch('stop-changed');
        }
    }

    public function previousStop(): void
    {
        if ($this->currentStopIndex > 0) {
            $this->currentStopIndex--;
            $this->deliveryData = [];
            $this->receiverName = '';
            $this->route->load([
                'routeStops' => fn ($q) => $q->orderBy('stop_order'),
                'routeStops.order.customer',
                'routeStops.order.items.product',
            ]);
            $this->initDeliveryData();
            $this->dispatch('stop-changed');
        }
    }

    private function saveSignature(string $base64, int $stopId): string
    {
        $data = explode(',', $base64);
        $image = base64_decode($data[1] ?? $data[0]);
        $path = "signatures/stop_{$stopId}_".time().'.png';
        \Storage::disk('public')->put($path, $image);

        return $path;
    }
}
