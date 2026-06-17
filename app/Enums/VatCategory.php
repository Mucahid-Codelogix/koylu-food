<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum VatCategory: string implements HasLabel
{
    case Low = 'low';
    case High = 'high';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Low => 'Laag (9%)',
            self::High => 'Hoog (21%)',
        };
    }

    public function rate(): float
    {
        return match ($this) {
            self::Low => 9.0,
            self::High => 21.0,
        };
    }
}
