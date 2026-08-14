<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Route;
use App\Models\User;
use App\Services\DeliveryNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class DeliveryPdfController extends Controller
{
    public function __construct(
        private DeliveryNoteService $deliveryNotes,
    ) {}

    /**
     * Werkbon: wordt gedrukt zodra de order is ingepland, met lege krat- en
     * handtekeningvakken om onderweg met pen in te vullen.
     */
    public function concept(Order $order): Response
    {
        $this->authorizeOrderAccess($order, allowCustomer: false);

        return $this->render($order, null);
    }

    /**
     * Definitieve leverbon: geleverde aantallen, kratten en handtekening.
     */
    public function download(Delivery $delivery): Response
    {
        $delivery->load(['order.customer', 'items']);

        $this->authorizeOrderAccess($delivery->order, allowCustomer: true);

        return $this->render($delivery->order, $delivery);
    }

    /**
     * Alle leverbonnen van één route in één PDF, zodat de chauffeur ze in één
     * keer kan uitprinten en meenemen. Stops die al geleverd zijn krijgen hun
     * definitieve bon, de rest een werkbon om met pen in te vullen.
     */
    public function routeNotes(Route $route): Response
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless(
            $user->role === UserRole::ADMIN || $route->driver_id === $user->id,
            403,
        );

        $route->load([
            'routeStops' => fn ($query) => $query->orderBy('stop_order'),
            'routeStops.order.customer',
            'routeStops.order.items',
            'routeStops.order.delivery.items',
            'routeStops.order.routeStops.route.driver',
        ]);

        $notes = $route->routeStops
            ->map(fn ($stop): array => $this->deliveryNotes->viewData(
                $stop->order,
                $stop->order->delivery,
            ))
            ->values()
            ->all();

        abort_if($notes === [], 404, 'Deze route heeft nog geen stops.');

        $pdf = Pdf::loadView('pdf.leverbon-route', [
            'route' => $route,
            'notes' => $notes,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(
            sprintf('leverbonnen-route-%s.pdf', $route->route_date?->format('Y-m-d') ?? $route->id),
        );
    }

    private function render(Order $order, ?Delivery $delivery): Response
    {
        $data = $this->deliveryNotes->viewData($order, $delivery);

        $pdf = Pdf::loadView('pdf.leverbon', $data)->setPaper('a4', 'portrait');

        return $pdf->download($this->deliveryNotes->filename($order, $data['isFinal']));
    }

    private function authorizeOrderAccess(Order $order, bool $allowCustomer): void
    {
        /** @var User $user */
        $user = auth()->user();

        match ($user->role) {
            UserRole::ADMIN => null,
            UserRole::DRIVER => abort_unless($this->driverOwnsOrder($user, $order), 403),
            UserRole::CUSTOMER => abort_unless(
                $allowCustomer && $order->customer_id === $user->customer_id,
                403,
            ),
        };
    }

    private function driverOwnsOrder(User $user, Order $order): bool
    {
        return $order->routeStops()
            ->whereHas('route', fn ($query) => $query->where('driver_id', $user->id))
            ->exists();
    }
}
