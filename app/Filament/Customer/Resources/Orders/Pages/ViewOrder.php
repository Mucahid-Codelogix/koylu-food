<?php

namespace App\Filament\Customer\Resources\Orders\Pages;

use App\Filament\Customer\Resources\Orders\OrderResource;
use App\Mail\OrderPlacedMail;
use App\Models\Order;
use App\Services\OrderRepeatService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadLeverbon')
                ->label('Leverbon downloaden')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => $this->record->delivery
                    ? route('leverbon.download', $this->record->delivery)
                    : null
                )
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->delivery !== null),

            Action::make('orderAgain')
                ->label('Opnieuw bestellen')
                ->icon('heroicon-o-arrow-path')
                ->button()
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Bevestig opnieuw bestellen')
                ->modalDescription('Dezelfde regels worden opnieuw besteld tegen de actuele prijzen. Regels die niet meer beschikbaar zijn, vallen weg.')
                ->modalSubmitActionLabel('Opnieuw bestellen')
                ->action(function (OrderRepeatService $orderRepeatService): void {
                    $result = $orderRepeatService->placeCopy($this->record);
                    $newOrder = $result['order'];

                    if (! $newOrder) {
                        Notification::make()
                            ->title('Geen enkele regel kon worden overgenomen')
                            ->body('De producten uit deze bestelling zijn niet meer beschikbaar.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->sendConfirmationMail($newOrder);

                    $notification = Notification::make()
                        ->title('Bestelling '.$newOrder->order_number.' geplaatst')
                        ->success();

                    if ($result['skipped'] !== []) {
                        $notification->body('Niet meer beschikbaar: '.implode(', ', $result['skipped']));
                    }

                    $notification->send();

                    $this->redirect(OrderResource::getUrl('view', ['record' => $newOrder]));
                }),
        ];
    }

    private function sendConfirmationMail(Order $order): void
    {
        $email = Auth::user()?->email;

        if (! $email) {
            return;
        }

        try {
            $order->load('items');
            Mail::to($email)->send(new OrderPlacedMail($order));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
