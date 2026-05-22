<?php

namespace App\Filament\Customer\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Shop extends Page
{
    protected string $view = 'filament.customer.pages.shop';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $title = 'Shop';

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
