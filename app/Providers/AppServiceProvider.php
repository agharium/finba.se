<?php

namespace App\Providers;

use App\Filament\Pages\Changelog;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        FilamentView::registerRenderHook(
            'panels::auth.login.form.after',
            fn () => view('auth.socialite.google')
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_BEFORE,
            fn (): ?\Illuminate\Contracts\View\View => auth()->check()
                ? view('filament.components.alpha-banner', [
                    'changelogUrl' => Changelog::getUrl(),
                ])
                : null,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): ?\Illuminate\Contracts\View\View => auth()->check()
                ? view('filament.components.pwa-install-button')
                : null,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): ?\Illuminate\Contracts\View\View => auth()->check()
                ? view('filament.components.pwa-shell')
                : null,
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn () => new HtmlString('
                <meta name="referrer" content="no-referrer">
                <meta name="theme-color" content="#0f1115">
                <meta name="mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-capable" content="yes">
                <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
                <meta name="apple-mobile-web-app-title" content="Finba.se">
                <link rel="icon" href="/favicon.ico" sizes="any">
                <link rel="icon" type="image/svg+xml" href="/favicon.svg">
                <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
                <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
                <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
                <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#16c04b">
                <link rel="manifest" href="/manifest.webmanifest">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/transactions.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/loans.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/dashboard.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/profile.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/onboarding.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/alpha-banner.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/changelog.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/roadmap.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/pwa.css') . '">
                <script type="module" src="' . Vite::asset('resources/js/pwa-manager.js') . '"></script>
            ')
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        VerifyEmail::createUrlUsing(function ($notifiable) {
            return URL::temporarySignedRoute(
                'filament.admin.auth.email-verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
        });
    }
}
