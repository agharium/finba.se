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
            PanelsRenderHook::HEAD_END,
            fn () => new HtmlString('
                <meta name="referrer" content="no-referrer">   
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/transactions.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/loans.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/dashboard.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/profile.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/onboarding.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/alpha-banner.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/changelog.css') . '">
                <link rel="stylesheet" href="' . Vite::asset('resources/css/filament/roadmap.css') . '">
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
