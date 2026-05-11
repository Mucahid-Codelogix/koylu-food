<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RouteStopStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case SKIPPED = 'skipped';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PENDING => 'Gepland',
            self::DELIVERED => 'Geleverd',
            self::SKIPPED => 'Overgeslagen',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::DELIVERED => 'success',
            self::SKIPPED => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::SKIPPED => 'heroicon-o-arrow-right-circle',
        };
    }
}
