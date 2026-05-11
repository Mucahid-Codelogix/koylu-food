<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Product informatie')
                    ->description('Basisinformatie van het product')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Productnaam')
                            ->required()
                            ->maxLength(255),

                        Select::make('supplier_id')
                            ->label('Leverancier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('unit')
                            ->label('Eenheid')
                            ->required()
                            ->placeholder('Bijv. kg, doos, stuk'),

                        TextInput::make('min_quantity')
                            ->label('Minimale afname')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1),

                        TextInput::make('price')
                            ->label('Verkoopprijs')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->step(0.01)
                            ->minValue(0),

                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true)
                            ->inline(false),

                        Textarea::make('description')
                            ->label('Omschrijving')
                            ->rows(5)
                            ->columnSpanFull(),

                    ]),

                Section::make('Product afbeelding')
                    ->description('Upload een productfoto')
                    ->schema([

                        FileUpload::make('image_path')
                            ->label('Afbeelding')
                            ->disk('public')
                            ->directory('products')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('250')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->columnSpanFull(),

                    ]),

            ]);
    }
}
