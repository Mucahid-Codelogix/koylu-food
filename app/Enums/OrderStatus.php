<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case PLACED = 'placed';
    case ROUTED = 'routed';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PLACED => 'Geplaatst',
            self::ROUTED => 'Ingepland',
            self::DELIVERED => 'Geleverd',
            self::CANCELLED => 'Geannuleerd',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PLACED => 'info',
            self::ROUTED => 'warning',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PLACED => 'heroicon-o-shopping-bag',
            self::ROUTED => 'heroicon-o-map',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
