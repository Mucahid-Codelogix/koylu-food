<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductType;
use App\Filament\Resources\Products\Schemas\ProductPackagingForm;
use App\Models\Product;
use App\Models\ProductPackaging;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PackagingsRelationManager extends RelationManager
{
    protected static string $relationship = 'packagings';

    protected static ?string $title = 'Verpakkingen';

    protected static ?string $modelLabel = 'verpakking';

    protected static ?string $pluralModelLabel = 'verpakkingen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product
            && $ownerRecord->product_type === ProductType::Standard;
    }

    public function form(Schema $schema): Schema
    {
        return ProductPackagingForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->with('productSuppliers.supplier'))
            ->emptyStateHeading('Nog geen verpakkingen')
            ->emptyStateDescription('Voeg minstens één verpakking toe met het gewicht in kg (bv. doos 10 kg).')
            ->emptyStateIcon('heroicon-o-archive-box')
            ->columns([
                TextColumn::make('packaging_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('weight_kg')
                    ->label('Gewicht')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' kg')
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Weergave')
                    ->formatStateUsing(fn (ProductPackaging $record): string => $record->displayLabel())
                    ->wrap(),
                TextColumn::make('productSuppliers.supplier.name')
                    ->label('Leveranciers')
                    ->badge()
                    ->placeholder('Alle')
                    ->listWithLineBreaks()
                    ->limitList(3),
                TextColumn::make('min_order_quantity')
                    ->label('Min. afname')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('Productstandaard'),
                IconColumn::make('is_default')
                    ->label('Standaard')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Verpakking toevoegen')
                    ->modalHeading('Nieuwe verpakking')
                    ->modalWidth(Width::Large),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Verpakking bewerken')
                    ->modalWidth(Width::Large),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
