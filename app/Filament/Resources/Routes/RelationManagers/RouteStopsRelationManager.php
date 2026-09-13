<?php

namespace App\Filament\Resources\Routes\RelationManagers;

use App\Enums\RouteStatus;
use App\Enums\RouteStopStatus;
use App\Filament\Support\TableActionGroup;
use App\Models\Order;
use App\Models\Route;
use App\Services\RouteWorkflowService;
use DomainException;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RouteStopsRelationManager extends RelationManager
{
    protected static string $relationship = 'routeStops';

    /**
     * Stops zijn ook direct bewerkbaar vanaf de view-pagina van een route,
     * zodat de planner niet eerst op "Bewerken" hoeft te klikken.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Een afgeronde route is administratief afgesloten en mag niet meer wijzigen.
     */
    protected function routeIsEditable(): bool
    {
        /** @var Route $route */
        $route = $this->getOwnerRecord();

        return $route->status !== RouteStatus::COMPLETED;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stop toevoegen')
                    ->description('Volgorde wordt automatisch bepaald. Sleep stops in de tabel om te herschikken.')
                    ->schema([
                        Select::make('order_id')
                            ->label('Bestelling')
                            ->searchable()
                            ->options(
                                fn (): array => Order::query()
                                    ->placed()
                                    ->notOnRoute()
                                    ->with('customer')
                                    ->orderByDesc('order_date')
                                    ->get()
                                    ->mapWithKeys(fn (Order $order) => [
                                        $order->id => "{$order->order_number} — {$order->customer->company_name}",
                                    ])
                                    ->all()
                            )
                            ->required()
                            ->native(false)
                            ->visibleOn('create'),
                    ]),

                Section::make('Status')
                    ->visibleOn('edit')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(collect(RouteStopStatus::cases())->mapWithKeys(
                                fn (RouteStopStatus $status) => [$status->value => $status->getLabel()]
                            )->all())
                            ->default(RouteStopStatus::PENDING->value)
                            ->required()
                            ->native(false),
                    ]),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Route stop')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('stop_order')
                            ->label('Volgorde')
                            ->numeric(),

                        TextEntry::make('order.order_number')
                            ->label('Bestelling')
                            ->copyable(),

                        TextEntry::make('order.customer.company_name')
                            ->label('Klant')
                            ->placeholder('-'),

                        TextEntry::make('order.customer.city')
                            ->label('Plaats')
                            ->placeholder('-'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                    ]),

                Section::make('Systeem')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Aangemaakt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),

                        TextEntry::make('updated_at')
                            ->label('Bijgewerkt')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stop_order')
            ->reorderable('stop_order', condition: $this->routeIsEditable())
            ->defaultSort('stop_order')
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Order nummer')
                    ->searchable(),
                TextColumn::make('order.customer.company_name')
                    ->label('Klant')
                    ->searchable(),
                TextColumn::make('order.customer.city')
                    ->label('Plaats')
                    ->searchable(),
                TextColumn::make('order.status')
                    ->label('Orderstatus')
                    ->badge(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Stop toevoegen')
                    ->visible(fn (): bool => $this->routeIsEditable())
                    ->action(function (array $data): void {
                        $route = $this->getOwnerRecord();
                        $order = Order::query()->findOrFail($data['order_id']);

                        try {
                            app(RouteWorkflowService::class)->assignOrderToRoute($route, $order);
                        } catch (DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Stop toegevoegd')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                TableActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (): bool => $this->routeIsEditable()),
                    DeleteAction::make()
                        ->visible(fn (): bool => $this->routeIsEditable()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->routeIsEditable()),
                ]),
            ]);
    }
}
