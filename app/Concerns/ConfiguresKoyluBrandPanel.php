<?php

namespace App\Concerns;

use Filament\Panel;
use Filament\Support\Colors\Color;

trait ConfiguresKoyluBrandPanel
{
    protected function configureKoyluBrand(Panel $panel): Panel
    {
        return $panel
            ->brandName(config('brand.name'))
            ->brandLogo(asset(config('brand.logo')))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset(config('brand.favicon')))
            ->colors([
                'primary' => Color::hex(config('brand.colors.red')),
                'success' => Color::hex(config('brand.colors.green')),
                'gray' => Color::Slate,
            ]);
    }
}
