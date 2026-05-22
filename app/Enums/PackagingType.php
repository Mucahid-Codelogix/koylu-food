<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PackagingType: string implements HasLabel
{
    case Box = 'box';
    case Tray = 'tray';
    case Bag = 'bag';
    case Crate = 'crate';
    case Piece = 'piece';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Box => 'Doos',
            self::Tray => 'Bakje',
            self::Bag => 'Zak',
            self::Crate => 'Krat',
            self::Piece => 'Stuk',
        };
    }
}
