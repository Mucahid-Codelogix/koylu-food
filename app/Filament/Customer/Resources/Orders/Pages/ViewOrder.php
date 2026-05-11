<?php

namespace App\Filament\Customer\Resources\Orders\Pages;

use App\Filament\Customer\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

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
                ->label('Order Again')
                ->button()
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Bevestig opnieuw bestellen')
                ->modalDescription('Weet je zeker dat je deze bestelling opnieuw wilt plaatsen? Alle items worden gekopieerd.')
                ->action(function () {
                    $user = auth()->user();

                    // Nieuwe order aanmaken
                    $newOrder = Order::create([
                        'order_number' => 'ORD-' . strtoupper(Str::random(6)),
                        'customer_id' => $user->customer_id,
                        'status' => 'placed',
                        'order_date' => now(),
                        'total_price' => $this->record->total_price,
                    ]);

                    // Kopieer items
                    foreach ($this->record->items as $item) {
                        OrderItem::create([
                            'order_id' => $newOrder->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'unit' => $item->unit,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                        ]);
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('Bestelling opnieuw geplaatst')
                        ->success()
                        ->send();
                }),
        ];
    }
}
