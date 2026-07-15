<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Filament\Pages\About;
use App\Filament\Pages\Changelog;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Profile;
use App\Filament\Pages\Roadmap;
use App\Filament\Pages\SendFeedback;
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
use Openplain\FilamentShadcnTheme\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->spa(hasPrefetching: true)
            ->unsavedChangesAlerts()
            ->login(Login::class)
            ->registration(Register::class)
            ->emailVerification()
            ->maxContentWidth('full')
            ->colors([
                'primary' => Color::Green,
                'danger' => Color::Rose,
            ])
            ->favicon(asset('favicon.ico'))
            ->brandLogo(asset('images/logo/light.png'))
            ->darkModeBrandLogo(asset('images/logo/dark.png'))
            ->brandLogoHeight('2rem')
            ->globalSearch(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                Profile::class,
                Changelog::class,
                Roadmap::class,
                SendFeedback::class,
                About::class,
            ])
            ->widgets([])
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
