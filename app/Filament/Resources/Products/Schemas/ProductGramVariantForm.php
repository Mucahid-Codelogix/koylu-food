<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductGramVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gramvariant')
                    ->description('Gewicht per stuk en doosconfiguratie voor hele kip')
                    ->columns(2)
                    ->schema([
                        TextInput::make('weight_grams')
                            ->label('Gewicht per stuk')
                            ->required()
                            ->numeric()
                            ->suffix('g')
                            ->minValue(1)
                            ->step(1),

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
                            ->step(0.001)
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
                    ]),
            ]);
    }
}
