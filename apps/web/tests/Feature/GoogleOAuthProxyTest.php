<?php

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Config;

it('excludes google oauth routes from filament spa navigation', function () {
    $exceptions = Filament::getPanel('admin')->getSpaUrlExceptions();

    expect($exceptions)
        ->toContain(url('/auth/google/redirect'))
        ->toContain(url('/auth/google/callback'))
        ->toContain('*/auth/google/redirect')
        ->toContain('*/auth/google/callback');

    // Mirror panel SPA settings into the view manager (as Filament does when serving the panel).
    FilamentView::spa(true, hasPrefetching: true);
    FilamentView::spaUrlExceptions($exceptions);

    expect(FilamentView::hasSpaMode(url('/auth/google/redirect')))->toBeFalse()
        ->and(FilamentView::hasSpaMode(url('/auth/google/callback')))->toBeFalse()
        ->and(FilamentView::hasSpaMode('https://app.finba.se/auth/google/redirect'))->toBeFalse()
        ->and(FilamentView::hasSpaMode(url('/login')))->toBeTrue();
});

it('renders the google login link for full browser navigation', function () {
    $html = $this->get('/login')->assertSuccessful()->getContent();

    expect($html)
        ->toContain('auth/google/redirect')
        ->toContain('Entrar com Google');

    preg_match('/<a\b[^>]*href="[^"]*auth\/google\/redirect[^"]*"[^>]*>/i', $html, $matches);

    expect($matches[0] ?? '')
        ->not->toBeEmpty()
        ->toContain('wire:navigate.ignore')
        ->not->toContain('wire:navigate.hover')
        ->not->toMatch('/\bwire:navigate(?!\.ignore)\b/');
});

it('uses trusted proxy headers for scheme host and port', function () {
    Config::set('app.url', 'https://app.finba.se');

    $this->call('GET', '/up', server: [
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'app.finba.se',
        'HTTP_X_FORWARDED_PORT' => '443',
        'REMOTE_ADDR' => '10.0.0.1',
        'SERVER_NAME' => 'finba-xxxx-uc.a.run.app',
        'HTTP_HOST' => 'finba-xxxx-uc.a.run.app',
        'HTTPS' => 'off',
    ])->assertSuccessful();

    expect(request()->getScheme())->toBe('https')
        ->and(request()->getHost())->toBe('app.finba.se')
        ->and(request()->getPort())->toBe(443)
        ->and(url('/auth/google/callback'))->toBe('https://app.finba.se/auth/google/callback')
        ->and(route('socialite.redirect', 'google'))->toBe('https://app.finba.se/auth/google/redirect');
});

it('publishes local logo paths without run.app or http absolute urls', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getBrandLogo())->toBe('/images/logo/light.png')
        ->and($panel->getDarkModeBrandLogo())->toBe('/images/logo/dark.png')
        ->and($panel->getFavicon())->toBe('/favicon.ico');

    $html = $this->get('/login')->assertSuccessful()->getContent();

    expect($html)
        ->toContain('/images/logo/light.png')
        ->not->toMatch('/https?:\/\/[^"\']*run\.app[^"\']*\/images\/logo\//i')
        ->not->toMatch('/(?:src|href)="https?:\/\/[^"]*\/images\/logo\/(?:light|dark)\.png"/i')
        ->toMatch('/(?:src|href)="\/images\/logo\/light\.png"/i');
});
