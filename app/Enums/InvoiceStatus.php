<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case CONCEPT = 'concept';
    case SENT = 'sent';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::CONCEPT => 'Concept',
            self::SENT => 'Verzonden',
            self::PAID => 'Betaald',
            self::OVERDUE => 'Verlopen',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CONCEPT => 'gray',
            self::SENT => 'warning',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CONCEPT => 'heroicon-o-document',
            self::SENT => 'heroicon-o-paper-airplane',
            self::PAID => 'heroicon-o-banknotes',
            self::OVERDUE => 'heroicon-o-exclamation-triangle',
        };
    }
}
