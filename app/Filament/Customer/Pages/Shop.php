<?php

namespace App\Filament\Customer\Pages;

use BackedEnum;
use Filament\Pages\Page;

class Shop extends Page
{
    protected string $view = 'filament.customer.pages.shop';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
}
