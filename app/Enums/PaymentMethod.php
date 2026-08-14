<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case BANK = 'bank';
    case CASH = 'cash';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::BANK => 'Op rekening',
            self::CASH => 'Contant',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BANK => 'gray',
            self::CASH => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::BANK => 'heroicon-o-building-library',
            self::CASH => 'heroicon-o-banknotes',
        };
    }
}
