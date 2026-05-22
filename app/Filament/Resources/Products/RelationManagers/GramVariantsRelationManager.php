<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Enums\ProductType;
use App\Filament\Resources\Products\Schemas\ProductGramVariantForm;
use App\Models\Product;
use App\Models\ProductGramVariant;
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

class GramVariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'gramVariants';

    protected static ?string $title = 'Gramvarianten';

    protected static ?string $modelLabel = 'gramvariant';

    protected static ?string $pluralModelLabel = 'gramvarianten';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Product
            && $ownerRecord->product_type === ProductType::WholeChicken;
    }

    public function form(Schema $schema): Schema
    {
        return ProductGramVariantForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('weight_grams')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->emptyStateHeading('Nog geen gramvarianten')
            ->emptyStateDescription('Voeg varianten toe (bv. 750 g, 1200 g) met stuks per doos en doosgewicht in kg.')
            ->emptyStateIcon('heroicon-o-scale')
            ->columns([
                TextColumn::make('weight_grams')
                    ->label('Gewicht')
                    ->formatStateUsing(fn (int $state): string => $state.' g')
                    ->sortable(),
                TextColumn::make('pieces_per_box')
                    ->label('Stuks/doos')
                    ->sortable(),
                TextColumn::make('box_weight_kg')
                    ->label('Doos (kg)')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' kg'),
                TextColumn::make('label')
                    ->label('Label')
                    ->formatStateUsing(fn (ProductGramVariant $record): string => $record->boxDescription())
                    ->wrap(),
                IconColumn::make('is_default')
                    ->label('Standaard')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Gramvariant toevoegen')
                    ->modalHeading('Nieuwe gramvariant')
                    ->modalWidth(Width::Large),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Gramvariant bewerken')
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
