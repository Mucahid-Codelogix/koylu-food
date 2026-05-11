<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factuurgegevens')
                    ->columns(2)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('Factuurnummer')
                            ->disabled()
                            ->dehydrated(),

                        Select::make('status')
                            ->label('Status')
                            ->options(InvoiceStatus::class)
                            ->required()
                            ->native(false),

                        DatePicker::make('invoice_date')
                            ->label('Factuurdatum')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y'),

                        DatePicker::make('due_date')
                            ->label('Vervaldatum')
                            ->required()
                            ->native(false)
                            ->displayFormat('d-m-Y'),

                        TextInput::make('customer_name')
                            ->label('Klant')
                            ->disabled()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($component, $record) {
                                $component->state($record?->order?->customer?->company_name);
                            }),
                    ]),

                Section::make('Bedragen')
                    ->description('Bedragen worden automatisch berekend op basis van de bestelling.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('subtotal_amount')
                            ->label('Subtotaal')
                            ->prefix('€')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('vat_amount')
                            ->label('BTW (21%)')
                            ->prefix('€')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('total_amount')
                            ->label('Totaal')
                            ->prefix('€')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Notities')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Opmerkingen')
                            ->placeholder('Eventuele opmerkingen bij deze factuur...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
