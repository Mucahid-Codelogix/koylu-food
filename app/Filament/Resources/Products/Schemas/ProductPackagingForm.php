<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\PackagingType;
use App\Models\ProductSupplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductPackagingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verpakking')
                    ->description('Gewicht en weergave in de shop')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('packaging_type')
                            ->label('Verpakkingstype')
                            ->options(PackagingType::class)
                            ->required()
                            ->native(false),

                        TextInput::make('weight_kg')
                            ->label('Gewicht per verpakking')
                            ->required()
                            ->numeric()
                            ->suffix('kg')
                            ->minValue(0.001)
                            ->step(0.001),

                        TextInput::make('label')
                            ->label('Weergavenaam')
                            ->placeholder('Optioneel, bv. Doos 12×750g')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('min_order_quantity')
                            ->label('Minimale afname')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->helperText('Leeg = standaard van het product'),

                        TextInput::make('sort_order')
                            ->label('Volgorde')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_default')
                            ->label('Standaardverpakking')
                            ->helperText('Wordt voorgeselecteerd in de shop')
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),

                        Select::make('productSuppliers')
                            ->label('Beschikbaar bij leveranciers')
                            ->helperText('Leeg laten = beschikbaar bij alle leveranciers van dit product. De eerste 10 leveranciers worden getoond; zoek voor meer.')
                            ->multiple()
                            ->searchable(['suppliers.name'])
                            ->preload()
                            ->relationship(
                                'productSuppliers',
                                modifyQueryUsing: function (Builder $query, $livewire, ?string $search = null): Builder {
                                    $owner = $livewire->getOwnerRecord();

                                    if ($owner === null) {
                                        return $query->whereRaw('0 = 1');
                                    }

                                    $query = $query
                                        ->where('product_suppliers.product_id', $owner->getKey())
                                        ->where('product_suppliers.is_active', true)
                                        ->join('suppliers', 'suppliers.id', '=', 'product_suppliers.supplier_id')
                                        ->select('product_suppliers.*')
                                        ->addSelect('suppliers.name')
                                        ->orderBy('suppliers.name')
                                        ->with('supplier:id,name');

                                    if (blank($search)) {
                                        $query->limit(10);
                                    }

                                    return $query;
                                },
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (ProductSupplier $record): string => $record->supplier?->name ?? "Leverancier #{$record->id}",
                            )
                            ->columnSpanFull()
                            ->native(false),
                    ]),
            ]);
    }
}
