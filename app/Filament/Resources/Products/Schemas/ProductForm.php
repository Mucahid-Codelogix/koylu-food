<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductType;
use App\Enums\VatCategory;
use App\Support\UploadStorage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Basisgegevens')
                    ->description('Naam, type en zichtbaarheid. Verpakkingen en leveranciers beheer je in de tabbladen hieronder (na opslaan).')
                    ->icon('heroicon-o-cube')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Productnaam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('product_type')
                            ->label('Producttype')
                            ->options(ProductType::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Toggle::make('allows_loading_substitute')
                            ->label('Alternatief toestaan bij laden')
                            ->helperText('Chauffeur/magazijn mag een andere gramvariant kiezen als de bestelde niet beschikbaar is')
                            ->visible(fn ($get): bool => $get('product_type') === ProductType::WholeChicken->value)
                            ->inline(false),

                        TextInput::make('min_order_quantity')
                            ->label('Standaard minimale afname')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->helperText('Geldt als er geen minimum per verpakking is ingesteld'),

                        Toggle::make('is_active')
                            ->label('Zichtbaar in shop')
                            ->default(true)
                            ->inline(false),

                        Select::make('vat_category')
                            ->label('BTW-categorie')
                            ->options(VatCategory::class)
                            ->default(VatCategory::High)
                            ->required()
                            ->native(false)
                            ->helperText('Bepaalt het btw-tarief op facturen voor nieuwe bestellingen (9% of 21%).'),

                        TextInput::make('exact_article_code')
                            ->label('Exact artikelcode')
                            ->maxLength(50)
                            ->helperText('Eén artikel per product in Exact. Leeg laten = automatisch KOYLU-P-{id} bij sync.'),

                        Textarea::make('description')
                            ->label('Omschrijving')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Productfoto')
                    ->description('Optionele afbeelding voor de webshop')
                    ->icon('heroicon-o-photo')
                    ->collapsed()
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Afbeelding')
                            ->disk(UploadStorage::diskName())
                            ->directory(UploadStorage::directory('products'))
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->maxSize(5120)
                            ->fetchFileInformation(false)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
