<?php

use App\Filament\Pages\About;
use App\Filament\Pages\Changelog;
use App\Filament\Pages\Roadmap;
use App\Filament\Pages\SendFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function aboutUser(): User
{
    return User::query()->create([
        'name' => 'About User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
    ]);
}

it('allows authenticated users to access about page', function () {
    config(['finba.creator.url' => null]);

    Livewire::actingAs(aboutUser())
        ->test(About::class)
        ->assertSuccessful()
        ->assertSee('Sobre o Finba.se')
        ->assertSee('O que é o Finba.se')
        ->assertSee('José Paulo Oliveira Filho')
        ->assertSee('LinkedIn')
        ->assertSee('https://www.linkedin.com/in/jose-paulo-oliveira-filho/')
        ->assertSee('GitHub')
        ->assertSee('https://github.com/agharium/finba.se')
        ->assertDontSee('Portfólio')
        ->assertSee('Beta')
        ->assertSee('v0.1.0-beta')
        ->assertSee('O Finba.se encontra-se em fase beta.')
        ->assertSee('Changelog')
        ->assertSee('Roadmap');
});

it('denies guests access to about page', function () {
    $this->get(About::getUrl())->assertRedirect();
});

it('shows portfolio link only when creator url is configured', function () {
    config(['finba.creator.url' => null]);

    Livewire::actingAs(aboutUser())
        ->test(About::class)
        ->assertDontSee('Portfólio')
        ->assertDontSee('https://agharium.dev');

    config(['finba.creator.url' => 'https://agharium.dev']);

    Livewire::actingAs(aboutUser())
        ->test(About::class)
        ->assertSee('Portfólio')
        ->assertSee('https://agharium.dev');
});

it('orders sistema navigation pages as expected', function () {
    expect(Changelog::getNavigationSort())->toBe(1010)
        ->and(Roadmap::getNavigationSort())->toBe(1020)
        ->and(SendFeedback::getNavigationSort())->toBe(1030)
        ->and(About::getNavigationSort())->toBe(1040)
        ->and(Changelog::getNavigationGroup())->toBe('Projeto')
        ->and(Roadmap::getNavigationGroup())->toBe('Projeto')
        ->and(SendFeedback::getNavigationGroup())->toBe('Projeto')
        ->and(About::getNavigationGroup())->toBe('Projeto')
        ->and(SendFeedback::getNavigationLabel())->toBe('Feedback')
        ->and(About::getNavigationLabel())->toBe('Sobre o Finba.se');
});
