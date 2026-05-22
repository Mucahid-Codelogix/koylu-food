<?php

namespace App\Providers\Filament;

use App\Concerns\ConfiguresKoyluBrandPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DriverPanelProvider extends PanelProvider
{
    use ConfiguresKoyluBrandPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureKoyluBrand($panel)
            ->id('driver')
            ->path('driver')
            ->profile(isSimple: false)
            ->login()
            ->viteTheme('resources/css/filament/driver/theme.css')
            ->discoverResources(in: app_path('Filament/Driver/Resources'), for: 'App\Filament\Driver\Resources')
            ->discoverPages(in: app_path('Filament/Driver/Pages'), for: 'App\Filament\Driver\Pages')
            ->discoverWidgets(in: app_path('Filament/Driver/Widgets'), for: 'App\Filament\Driver\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
