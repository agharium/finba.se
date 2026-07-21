<?php

use App\Filament\Pages\Profile;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    fakeGeoContractApi();
});

function profileNavigationUser(array $settings = []): User
{
    $user = User::query()->create([
        'name' => 'Profile Navigation User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => array_merge([
            'advanced' => false,
            'tither' => false,
            'accounts_receivable' => false,
            'locale' => 'pt_BR',
        ], $settings),
        'locale' => 'pt_BR',
    ]);

    $user->update(['geo_city_id' => 1001]);

    return $user->fresh();
}

it('clears accounts receivable in profile form when advanced mode is disabled', function () {
    $user = profileNavigationUser([
        'advanced' => true,
        'accounts_receivable' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.advanced', false)
        ->assertSet('data.accounts_receivable', false);
});

it('redirects with full reload after profile save', function () {
    $user = profileNavigationUser(['advanced' => false]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.advanced', true)
        ->set('data.accounts_receivable', true)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    $fresh = $user->fresh();

    expect($fresh->hasAdvancedMode())->toBeTrue()
        ->and($fresh->usesAccountsReceivable())->toBeTrue();
});

it('reflects advanced navigation visibility after profile settings change', function () {
    $user = profileNavigationUser(['advanced' => false]);

    auth()->setUser($user);

    expect(PersonResource::shouldRegisterNavigation())->toBeFalse()
        ->and(LoanResource::shouldRegisterNavigation())->toBeFalse();

    $user->update([
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
        ],
    ]);

    auth()->setUser($user->fresh());

    expect(PersonResource::shouldRegisterNavigation())->toBeTrue()
        ->and(LoanResource::shouldRegisterNavigation())->toBeTrue();
});

it('does not register city management in filament navigation', function () {
    $user = profileNavigationUser(['advanced' => true]);

    $this->actingAs($user);

    $resourceClasses = Filament::getPanel('admin')->getResources();

    $cityResources = collect($resourceClasses)
        ->filter(fn (string $class): bool => str_contains($class, 'Cities\\'))
        ->values()
        ->all();

    expect($cityResources)->toBeEmpty();

    $navigationLabels = collect(Filament::getNavigation())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->all();

    expect($navigationLabels)->not->toContain('Cidades');
});

it('hides accounts receivable navigation when child flag is disabled', function () {
    $user = profileNavigationUser([
        'advanced' => true,
        'accounts_receivable' => false,
    ]);

    auth()->setUser($user);

    expect(LoanResource::shouldRegisterNavigation())->toBeFalse();
});

it('shows tither state from refreshed user after profile save', function () {
    $user = profileNavigationUser(['tither' => false]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.tither', true)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    expect(auth()->user()->isTither())->toBeTrue();
});
