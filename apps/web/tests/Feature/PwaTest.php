<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function pwaUser(): User
{
    return User::query()->create([
        'name' => 'PWA User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'onboarding_completed_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('serves a valid web app manifest', function () {
    $response = $this->get('/manifest.webmanifest');

    $response->assertSuccessful();

    $manifest = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->id->toBe('/')
        ->name->toBe('Finba.se')
        ->short_name->toBe('Finba.se')
        ->start_url->toBe('/')
        ->display->toBe('standalone')
        ->orientation->toBe('any')
        ->theme_color->toBe('#0f1115')
        ->background_color->toBe('#0f1115')
        ->icons->toBeArray()
        ->and(collect($manifest['icons'])->pluck('src')->all())
        ->toContain('/pwa/icon-192x192.png')
        ->toContain('/pwa/icon-512x512.png')
        ->toContain('/pwa/maskable-icon-512x512.png');

    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
});

it('exposes required favicon and pwa static assets', function () {
    collect([
        'favicon.ico',
        'favicon.svg',
        'favicon-16x16.png',
        'favicon-32x32.png',
        'apple-touch-icon.png',
        'safari-pinned-tab.svg',
        'offline.html',
        'service-worker.js',
        'manifest.webmanifest',
        'pwa/icon-96x96.png',
        'pwa/icon-144x144.png',
        'pwa/icon-192x192.png',
        'pwa/icon-384x384.png',
        'pwa/icon-512x512.png',
        'pwa/maskable-icon-192x192.png',
        'pwa/maskable-icon-512x512.png',
    ])->each(fn (string $path) => expect(public_path($path))->toBeFile());

    $this->get('/offline.html')->assertSuccessful()->assertSee('Você está sem conexão');
    $this->get('/service-worker.js')->assertSuccessful();
});

it('renders pwa metadata and install mount points for authenticated users', function () {
    $response = $this->actingAs(pwaUser())->get('/');

    $response->assertSuccessful()
        ->assertSee('Instalar aplicativo', false)
        ->assertSee('finba-pwa-install', false)
        ->assertSee('finba-pwa-shell', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee('theme-color', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('pwa-manager', false);
});

it('does not render the topbar global search for authenticated users', function () {
    $this->actingAs(pwaUser())
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee('fi-global-search', false)
        ->assertSee('finba-pwa-install', false);
});

it('keeps guests on the normal authentication flow', function () {
    $this->get('/')->assertRedirect();
});

it('keeps the service worker conservative about private data', function () {
    $source = file_get_contents(public_path('service-worker.js'));

    expect($source)
        ->toContain("method !== 'GET'")
        ->toContain('/offline.html')
        ->toContain('/livewire/')
        ->toContain('/logout')
        ->toContain('/login')
        ->not->toContain('/dashboard')
        ->not->toContain('BackgroundSync')
        ->not->toContain('indexedDB');
});

it('disables filament global search in the admin panel', function () {
    expect(filament()->getCurrentOrDefaultPanel()?->getGlobalSearchProvider())->toBeNull();
});

it('centers pwa dialogs with a full-viewport overlay', function () {
    $css = file_get_contents(resource_path('css/filament/pwa.css'));
    $shell = file_get_contents(resource_path('views/filament/components/pwa-shell.blade.php'));

    expect($css)
        ->toContain('position: fixed')
        ->toContain('inset: 0')
        ->toContain('align-items: center')
        ->toContain('justify-content: center')
        ->and($shell)
        ->toContain('x-teleport="body"')
        ->toContain('@cancel.prevent');
});

it('lists pwa as completed in the roadmap', function () {
    $roadmap = require resource_path('data/roadmap.php');

    $item = collect($roadmap)
        ->flatMap(fn (array $category) => $category['items'])
        ->firstWhere('title', 'PWA instalável');

    expect($item)->not->toBeNull()
        ->and($item['status'])->toBe('completed');
});
