<?php

namespace App\Filament\Driver\Pages;

use App\Enums\DeliveryStatus;
use App\Enums\RouteStopStatus;
use App\Models\CrateTransaction;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\OrderItem;
use App\Models\Route;
use App\Models\RouteStop;
use App\Services\CashPaymentService;
use App\Services\InvoiceService;
use App\Services\OrderWorkflowService;
use App\Services\RouteWorkflowService;
use App\Support\UploadStorage;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
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

    public string $driverSignature = '';

    public string $receiverName = '';

    public int $cratesGiven = 0;

    public int $cratesReturned = 0;

    public bool $paidInCash = false;

    public string $cashAmount = '';

    public function mount(): void
    {
        $routeId = request()->query('routeId') ?? request()->route('routeId');

        $this->route = Route::with([
            'driver',
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
        $this->driverSignature = '';
        $this->receiverName = '';
        $this->cratesGiven = 0;
        $this->cratesReturned = 0;
        $this->paidInCash = false;
        $this->cashAmount = '';
        $this->deliveryData = [];

        $this->dispatch('clear-signature');

        $existingDelivery = Delivery::with('items')
            ->where('order_id', $stop->order_id)
            ->first();

        foreach ($stop->order->items as $item) {
            $existingItem = $existingDelivery?->items
                ->firstWhere('order_item_id', $item->id);

            $loadedQuantity = $item->loaded_packaging_quantity !== null
                ? (float) $item->loaded_packaging_quantity
                : null;

            $this->deliveryData[$item->id] = [
                'product_name' => $item->product_name,
                'supplier_name' => $item->supplier_name,
                'unit' => $item->unit,
                'ordered_quantity' => $item->quantity,
                'loaded_quantity' => $loadedQuantity,
                'delivered_quantity' => $existingItem?->delivered_quantity
                    ?? $loadedQuantity
                    ?? $item->quantity,
                'is_missed' => $existingItem ? $existingItem->delivered_quantity == 0 : false,
                'missed_reason' => $existingItem?->missed_reason ?? '',
                'return_note' => $existingItem?->return_note ?? $this->loadingCorrectionNote($item),
                'actual_weight_kg' => $item->loaded_actual_weight_kg,
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

    /**
     * Als er tijdens het laden minder (of meer) is meegenomen dan besteld, geven we
     * de chauffeur een kant-en-klare retour-notitie zodat de afwijking herleidbaar blijft.
     */
    private function loadingCorrectionNote(OrderItem $item): string
    {
        if ($item->loaded_packaging_quantity === null) {
            return '';
        }

        $ordered = (float) $item->quantity;
        $loaded = (float) $item->loaded_packaging_quantity;

        if (abs($ordered - $loaded) <= 0.001) {
            return '';
        }

        return sprintf(
            'Correctie bij laden: origineel %s, geladen %s (%s)',
            $this->formatQuantity($ordered),
            $this->formatQuantity($loaded),
            $item->unit,
        );
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, ',', ''), '0'), ',');
    }

    private function decimalInput(mixed $value): ?float
    {
        $raw = trim((string) $value);

        return $raw !== '' ? (float) str_replace(',', '.', $raw) : null;
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
                ->title('Handtekening ontvanger ontbreekt')
                ->warning()
                ->send();

            return;
        }

        if (empty($this->driverSignature)) {
            Notification::make()
                ->title('Handtekening bezorger ontbreekt')
                ->body('Teken zelf ook voor de aflevering.')
                ->warning()
                ->send();

            return;
        }

        $signaturePath = $this->saveSignature($this->signature, $stop->id, 'ontvanger');
        $driverSignaturePath = $this->saveSignature($this->driverSignature, $stop->id, 'bezorger');
        $deliveryStatus = $this->resolveDeliveryStatus($stop);

        $delivery = Delivery::updateOrCreate(
            ['order_id' => $stop->order_id],
            [
                'delivered_at' => now(),
                'receiver_name' => $this->receiverName,
                'signature_path' => $signaturePath,
                'driver_signature_path' => $driverSignaturePath,
                'driver_name' => $this->route->driver?->name,
                'status' => $deliveryStatus,
            ]
        );

        foreach ($stop->order->items as $orderItem) {
            $data = $this->deliveryLineFor($orderItem->id);

            DeliveryItem::updateOrCreate(
                [
                    'delivery_id' => $delivery->id,
                    'order_item_id' => $orderItem->id,
                ],
                [
                    'product_id' => $orderItem->product_id,
                    'ordered_quantity' => $data['ordered_quantity'],
                    'delivered_quantity' => $data['is_missed'] ? 0 : $data['delivered_quantity'],
                    'missed_reason' => $data['is_missed'] ? $data['missed_reason'] : null,
                    'return_note' => $this->returnNoteForDeliveryItem($data),
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

            $invoice = app(InvoiceService::class)->createFromDelivery($delivery->fresh('items'));

            if ($this->paidInCash) {
                app(CashPaymentService::class)->registerCashPayment(
                    $invoice,
                    $this->decimalInput($this->cashAmount),
                );
            }
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

    public function skipStopAction(): Action
    {
        return Action::make('skipStop')
            ->label('Overslaan')
            ->icon(Heroicon::Forward)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Stop overslaan?')
            ->modalDescription('Weet je zeker dat je deze stop wilt overslaan? Je kunt dit later hervatten via "Levering hervatten".')
            ->modalSubmitActionLabel('Ja, overslaan')
            ->modalCancelActionLabel('Annuleren')
            ->modalIcon(Heroicon::OutlinedForward)
            ->extraAttributes(['class' => 'w-full justify-center'])
            ->action(fn (): mixed => $this->skipStop());
    }

    public function saveDeliveryAction(): Action
    {
        $isLastStop = fn (): bool => $this->currentStopIndex >= $this->getTotalStops() - 1;

        return Action::make('saveDelivery')
            ->label(fn (): string => $isLastStop() ? 'Route afronden' : 'Levering bevestigen')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (): string => $isLastStop() ? 'Route afronden?' : 'Levering bevestigen?')
            ->modalDescription('Weet je zeker dat je deze levering wilt bevestigen? Daarna kan niets meer worden gewijzigd.')
            ->modalSubmitActionLabel(fn (): string => $isLastStop() ? 'Ja, route afronden' : 'Ja, bevestigen')
            ->modalCancelActionLabel('Annuleren')
            ->modalIcon(Heroicon::OutlinedCheckCircle)
            ->extraAttributes(['class' => 'w-full justify-center py-4 text-lg'])
            ->action(fn (): mixed => $this->saveDelivery());
    }

    public function resumeStop(): void
    {
        $stop = $this->getCurrentStop();

        if ($stop->status !== RouteStopStatus::SKIPPED) {
            Notification::make()->title('Deze stop kan niet worden hervat')->warning()->send();

            return;
        }

        app(RouteWorkflowService::class)->reopenStop($this->route, $stop);

        $this->route->refresh();
        $this->route->load([
            'routeStops' => fn ($q) => $q->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items.product',
        ]);
        $this->initDeliveryData();
        $this->dispatch('stop-changed');

        Notification::make()
            ->title('Stop hervat')
            ->body('Je kunt deze levering nu alsnog afronden.')
            ->success()
            ->send();
    }

    public function previousStop(): void
    {
        if ($this->currentStopIndex > 0) {
            $this->currentStopIndex--;
            $this->reloadStopsAndInit();
        }
    }

    public function nextStop(): void
    {
        if ($this->currentStopIndex < $this->getTotalStops() - 1) {
            $this->currentStopIndex++;
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

    protected function resolveDeliveryStatus(RouteStop $stop): DeliveryStatus
    {
        $lines = $stop->order->items->map(
            fn ($item): array => $this->deliveryLineFor($item->id)
        );

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

    /**
     * Genormaliseerde leveringsregel voor één orderregel, met veilige defaults.
     * Livewire kan onvolledige entries in $deliveryData achterlaten; door hier
     * altijd alle sleutels te garanderen kunnen opslag en status niet crashen.
     *
     * @return array{
     *     ordered_quantity: float|int|string,
     *     delivered_quantity: float|int|string,
     *     is_missed: bool,
     *     missed_reason: string,
     *     return_note: string,
     * }
     */
    private function deliveryLineFor(int $orderItemId): array
    {
        $data = $this->deliveryData[$orderItemId] ?? [];

        return [
            'ordered_quantity' => $data['ordered_quantity'] ?? 0,
            'delivered_quantity' => $data['delivered_quantity'] ?? 0,
            'is_missed' => (bool) ($data['is_missed'] ?? false),
            'missed_reason' => $data['missed_reason'] ?? '',
            'return_note' => $data['return_note'] ?? '',
        ];
    }

    /**
     * @param  array{
     *     is_missed: bool,
     *     ordered_quantity: float|int|string,
     *     delivered_quantity: float|int|string,
     *     return_note?: ?string,
     * }  $data
     */
    protected function returnNoteForDeliveryItem(array $data): ?string
    {
        if ($data['is_missed']) {
            return null;
        }

        if ((float) $data['delivered_quantity'] >= (float) $data['ordered_quantity']) {
            return null;
        }

        $note = trim((string) ($data['return_note'] ?? ''));

        return filled($note) ? $note : null;
    }

    private function saveSignature(string $base64, int $stopId, string $role): string
    {
        $data = explode(',', $base64);
        $image = base64_decode($data[1] ?? $data[0]);
        $path = UploadStorage::directory('signatures')."/stop_{$stopId}_{$role}_".time().'.png';
        UploadStorage::disk()->put($path, $image);

        return $path;
    }
}
