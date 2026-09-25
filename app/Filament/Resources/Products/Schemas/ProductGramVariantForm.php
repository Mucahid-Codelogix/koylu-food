<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ProductSupplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductGramVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gramvariant')
                    ->description('Gewicht per stuk en doosconfiguratie voor hele kip')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('weight_grams')
                            ->label('Gewicht per stuk (gram)')
                            ->required()
                            ->numeric()
                            ->suffix('g')
                            ->minValue(1)
                            ->step(1)
                            ->live(debounce: 400)
                            ->helperText(fn (mixed $state): string => self::weightHelperText($state)),

                        TextInput::make('pieces_per_box')
                            ->label('Stuks per doos')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(1),

                        TextInput::make('box_weight_kg')
                            ->label('Gewicht per doos')
                            ->required()
                            ->numeric()
                            ->suffix('kg')
                            ->minValue(0.001)
                            ->helperText('Totaalgewicht van één doos voor deze variant'),

                        TextInput::make('label')
                            ->label('Weergavenaam')
                            ->placeholder('Optioneel, bv. 750 g premium')
                            ->maxLength(255),

                        TextInput::make('sort_order')
                            ->label('Volgorde')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        Toggle::make('is_default')
                            ->label('Standaardvariant')
                            ->helperText('Wordt voorgeselecteerd in de shop')
                            ->inline(false),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),

                        Select::make('productSuppliers')
                            ->label('Beschikbaar bij slachthuizen/leveranciers')
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

    /**
     * Toont het getypte gewicht meteen in kilo's, zodat een invoer in kg direct opvalt.
     */
    public static function weightHelperText(mixed $state): string
    {
        if (blank($state) || ! is_numeric($state)) {
            return 'In hele grammen, bv. 1200 voor 1,2 kg';
        }

        $grams = (float) $state;
        $kilos = '= '.number_format($grams / 1000, 3, ',', '.').' kg';

        if ($grams < 100) {
            return $kilos.' — bedoelde je '.number_format($grams * 1000, 0, ',', '.').' g? Vul het gewicht in grammen in.';
        }

        return $kilos;
    }
}
