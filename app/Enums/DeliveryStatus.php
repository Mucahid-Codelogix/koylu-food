<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DeliveryStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case PARTIAL = 'partial';
    case FAILED = 'failed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PENDING => 'In afwachting',
            self::DELIVERED => 'Geleverd',
            self::PARTIAL => 'Gedeeltelijk geleverd',
            self::FAILED => 'Mislukt',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::DELIVERED => 'success',
            self::PARTIAL => 'warning',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::PARTIAL => 'heroicon-o-exclamation-circle',
            self::FAILED => 'heroicon-o-x-circle',
        };
    }
}
