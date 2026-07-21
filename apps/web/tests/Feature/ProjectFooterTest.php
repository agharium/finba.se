<?php

use App\Filament\Pages\About;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function footerUser(): User
{
    return User::query()->create([
        'name' => 'Footer User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'onboarding_completed_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('renders footer authorship markup', function () {
    config(['finba.creator.url' => null]);

    $html = view('filament.components.project-footer')->render();

    expect($html)
        ->toContain('Finba.se © '.now()->year)
        ->toContain('Desenvolvido por')
        ->toContain('José Paulo Oliveira Filho')
        ->toContain('Beta')
        ->toContain('v0.1.0-beta')
        ->toContain('AGPL v3')
        ->not->toContain('https://agharium.dev');
});

it('links creator name in footer when configured', function () {
    config(['finba.creator.url' => 'https://agharium.dev']);

    $html = view('filament.components.project-footer')->render();

    expect($html)
        ->toContain('https://agharium.dev')
        ->toContain('José Paulo Oliveira Filho')
        ->toContain('AGPL v3');
});

it('includes footer authorship on authenticated panel pages', function () {
    config(['finba.creator.url' => null]);

    $this->actingAs(footerUser())
        ->get(About::getUrl())
        ->assertOk()
        ->assertSee('Finba.se © '.now()->year, false)
        ->assertSee('Desenvolvido por', false)
        ->assertSee('AGPL v3', false)
        ->assertSee('Beta', false)
        ->assertSee('v0.1.0-beta', false)
        ->assertSee('José Paulo Oliveira Filho', false);
});
