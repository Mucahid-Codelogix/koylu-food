<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RouteStatus: string implements HasColor, HasIcon, HasLabel
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PLANNED => 'Gepland',
            self::IN_PROGRESS => 'Onderweg',
            self::COMPLETED => 'Afgerond',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PLANNED => 'warning',
            self::IN_PROGRESS => 'info',
            self::COMPLETED => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PLANNED => 'heroicon-o-clock',
            self::IN_PROGRESS => 'heroicon-o-truck',
            self::COMPLETED => 'heroicon-o-check-circle',
        };
    }
}
