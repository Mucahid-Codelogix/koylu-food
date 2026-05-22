<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    public function getSubheading(): ?string
    {
        $packagings = $this->getRecord()->packagings()->where('is_active', true)->count();
        $suppliers = $this->getRecord()->productSuppliers()->where('is_active', true)->count();

        if ($packagings === 0 || $suppliers === 0) {
            return 'Let op: dit product is pas zichtbaar in de shop met minstens één actieve verpakking én leverancier.';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
