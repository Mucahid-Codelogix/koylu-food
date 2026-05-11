<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum UserRole: string implements HasLabel
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case DRIVER = 'driver';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::CUSTOMER => 'Klant',
            self::DRIVER => 'Chauffeur',
        };
    }
}
