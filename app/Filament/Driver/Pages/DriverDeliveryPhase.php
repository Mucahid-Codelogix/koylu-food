<?php

namespace App\Filament\Driver\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\RouteStopStatus;
use App\Models\CrateTransaction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Services\InvoiceService;
use App\Services\OrderWorkflowService;
use App\Services\RouteWorkflowService;
use App\Support\UploadStorage;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;

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

        try {
            app(RouteWorkflowService::class)->assertCanAccessDelivery($this->route, auth()->user());
        } catch (AuthorizationException $e) {
            abort(403, $e->getMessage());
        } catch (DomainException $e) {
            Notification::make()->title($e->getMessage())->warning()->send();
            $this->redirect(DriverDashboard::getUrl());

            return;
        }

        $firstPendingIndex = app(RouteWorkflowService::class)->firstPendingStopIndex($this->route);

        if ($firstPendingIndex === false) {
            app(RouteWorkflowService::class)->completeRouteIfReady($this->route);
            $this->redirect(DriverDashboard::getUrl());

            return;
        }

        $this->currentStopIndex = $firstPendingIndex;
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

        $existingCrates = CrateTransaction::where('route_id', $this->route->id)
            ->where('customer_id', $stop->order->customer_id)
            ->latest()
            ->first();

        if ($existingCrates) {
            $this->cratesGiven = $existingCrates->crates_given;
            $this->cratesReturned = $existingCrates->crates_returned;
        }

        if ($existingDelivery) {
            $this->receiverName = $existingDelivery->receiver_name ?? '';
        }
    }

    public function saveDelivery(): void
    {
        $stop = $this->getCurrentStop();
        $workflows = app(RouteWorkflowService::class);

        if ($stop->status !== RouteStopStatus::PENDING) {
            Notification::make()->title('Deze stop is al afgehandeld')->warning()->send();

            return;
        }

        if (empty($this->signature)) {
            Notification::make()
                ->title('Handtekening ontbreekt')
                ->warning()
                ->send();

            return;
        }

        $signaturePath = $this->saveSignature($this->signature, $stop->id);
        $deliveryStatus = $this->resolveDeliveryStatus();

        $delivery = Delivery::updateOrCreate(
            ['order_id' => $stop->order_id],
            [
                'delivered_at' => now(),
                'receiver_name' => $this->receiverName,
                'signature_path' => $signaturePath,
                'status' => $deliveryStatus,
            ]
        );

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

        if ($this->cratesGiven > 0 || $this->cratesReturned > 0) {
            CrateTransaction::create([
                'customer_id' => $stop->order->customer_id,
                'route_id' => $this->route->id,
                'crates_given' => $this->cratesGiven,
                'crates_returned' => $this->cratesReturned,
            ]);
        }

        $workflows->markStopDelivered($stop);

        if ($deliveryStatus !== DeliveryStatus::FAILED) {
            app(OrderWorkflowService::class)->markAsDelivered($stop->order);
            app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));
        }

        Notification::make()
            ->title('Levering opgeslagen!')
            ->success()
            ->send();

        $this->advanceToNextPendingOrComplete();
    }

    public function skipStop(): void
    {
        $stop = $this->getCurrentStop();

        if ($stop->status !== RouteStopStatus::PENDING) {
            Notification::make()->title('Deze stop kan niet worden overgeslagen')->warning()->send();

            return;
        }

        app(RouteWorkflowService::class)->skipStop($stop);

        Notification::make()
            ->title('Stop overgeslagen')
            ->success()
            ->send();

        $this->advanceToNextPendingOrComplete();
    }

    public function previousStop(): void
    {
        if ($this->currentStopIndex > 0) {
            $this->currentStopIndex--;
            $this->reloadStopsAndInit();
        }
    }

    protected function advanceToNextPendingOrComplete(): void
    {
        $this->route->refresh();
        $this->route->load([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
        ]);

        $workflows = app(RouteWorkflowService::class);
        $nextIndex = $workflows->firstPendingStopIndex($this->route);

        if ($nextIndex === false) {
            $workflows->completeRouteIfReady($this->route);
            $this->redirect(DriverDashboard::getUrl());

            return;
        }

        $this->currentStopIndex = $nextIndex;
        $this->initDeliveryData();
        $this->dispatch('stop-changed');
    }

    protected function reloadStopsAndInit(): void
    {
        $this->route->load([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
        ]);
        $this->initDeliveryData();
        $this->dispatch('stop-changed');
    }

    protected function resolveDeliveryStatus(): DeliveryStatus
    {
        $lines = collect($this->deliveryData);

        $deliveredLines = $lines->filter(
            fn (array $data): bool => ! $data['is_missed'] && (float) $data['delivered_quantity'] > 0
        );

        if ($deliveredLines->isEmpty()) {
            return DeliveryStatus::FAILED;
        }

        $partial = $lines->contains(function (array $data): bool {
            if ($data['is_missed']) {
                return true;
            }

            return (float) $data['delivered_quantity'] < (float) $data['ordered_quantity'];
        });

        return $partial ? DeliveryStatus::PARTIAL : DeliveryStatus::DELIVERED;
    }

    private function saveSignature(string $base64, int $stopId): string
    {
        $data = explode(',', $base64);
        $image = base64_decode($data[1] ?? $data[0]);
        $path = UploadStorage::directory('signatures')."/stop_{$stopId}_".time().'.png';
        UploadStorage::disk()->put($path, $image);

        return $path;
    }
}
