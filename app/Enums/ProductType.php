<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ProductType: string implements HasLabel
{
    case Standard = 'standard';
    case WholeChicken = 'whole_chicken';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Standard => 'Standaard',
            self::WholeChicken => 'Hele kip',
        };
    }
}
