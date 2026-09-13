<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * Rij-acties in tabellen staan altijd in één "Acties"-dropdown, zodat elke
 * tabel er hetzelfde uitziet, ongeacht het aantal acties.
 */
class TableActionGroup
{
    /**
     * @param  array<Action|ActionGroup>  $actions
     */
    public static function make(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label('Acties')
            ->icon('heroicon-o-ellipsis-vertical')
            ->color('gray')
            ->button();
    }
}
