<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\City;
use App\Models\User;
use App\Services\UserCityService;
use App\Services\UserPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function onboardingUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Onboarding User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['locale' => 'pt-BR'],
        'onboarding_completed_at' => null,
    ], $overrides));
}

function onboardingCompletionData(array $overrides = []): array
{
    return array_merge([
        'locale' => 'pt-BR',
        'default_country_code' => 'BR',
        'default_region_code' => 'RS',
        'default_city_id' => null,
        'advanced' => false,
        'tither' => false,
        'accounts_receivable' => false,
    ], $overrides);
}

function submitOnboardingWizard($component, User $user, array $overrides = [])
{
    $data = onboardingCompletionData($overrides);

    if (blank($data['default_city_id'])) {
        $data['default_city_id'] = app(UserCityService::class)
            ->findOrCreate($user, 'BR', $data['default_region_code'], 'Tramandaí')
            ->id;
    }

    return $component
        ->setActionData($data)
        ->goToNextWizardStep()
        ->goToNextWizardStep()
        ->callMountedAction();
}

it('shows onboarding automatically for a new user on the dashboard', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertActionMounted('onboarding');
});

it('does not show onboarding after completion', function () {
    $user = onboardingUser([
        'onboarding_completed_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSet('mountedActions', []);
});

it('stores BR internally when onboarding locale is pt-BR', function () {
    $user = onboardingUser();

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
    )->assertRedirect(Dashboard::getUrl());

    expect($user->fresh()->default_country_code)->toBe('BR');
});

it('persists state and city defaults from onboarding', function () {
    $user = onboardingUser();
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Osório');

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
        ['default_city_id' => $city->id],
    )->assertRedirect(Dashboard::getUrl());

    $fresh = $user->fresh();

    expect($fresh->default_region_code)->toBe('RS')
        ->and($fresh->default_city_id)->toBe($city->id)
        ->and($fresh->defaultCity?->name)->toBe('Osório');
});

it('creates or reuses a personal city during onboarding', function () {
    $user = onboardingUser();
    $existing = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
        ['default_city_id' => $existing->id],
    )->assertRedirect(Dashboard::getUrl());

    expect($user->fresh()->default_city_id)->toBe($existing->id)
        ->and(City::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('clears accounts receivable in onboarding when advanced mode is disabled', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->mountAction('onboarding')
        ->setActionData([
            'advanced' => true,
            'accounts_receivable' => true,
        ])
        ->setActionData([
            'advanced' => false,
        ])
        ->assertActionDataSet([
            'accounts_receivable' => false,
        ]);
});

it('saves accounts receivable only when advanced mode is enabled', function () {
    $user = onboardingUser();

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
        [
            'advanced' => true,
            'accounts_receivable' => true,
        ],
    )->assertRedirect(Dashboard::getUrl());

    $fresh = $user->fresh();

    expect($fresh->hasAdvancedMode())->toBeTrue()
        ->and($fresh->usesAccountsReceivable())->toBeTrue();
});

it('saves onboarding completion timestamp', function () {
    $user = onboardingUser();

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
    )->assertRedirect(Dashboard::getUrl());

    expect($user->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('reflects enabled features in navigation after onboarding completion', function () {
    $user = onboardingUser();
    $preferences = app(UserPreferencesService::class);
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    $preferences->completeOnboarding($user, onboardingCompletionData([
        'advanced' => true,
        'accounts_receivable' => true,
        'tither' => true,
        'default_city_id' => $city->id,
    ]));

    auth()->setUser($user->fresh());

    expect(PersonResource::shouldRegisterNavigation())->toBeTrue()
        ->and(LoanResource::shouldRegisterNavigation())->toBeTrue();
});

it('does not mark onboarding complete when skipping', function () {
    $user = onboardingUser();

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('onboardingSkippedThisVisit', true)
        ->call('unmountAction');

    expect($user->fresh()->onboarding_completed_at)->toBeNull();
});

it('does not reopen onboarding in the same visit after skipping', function () {
    $user = onboardingUser();

    $component = Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->set('onboardingSkippedThisVisit', true)
        ->call('unmountAction');

    $component->instance()->mount();

    expect($component->instance()->mountedActions)->toBeEmpty();
});

it('prevents a user from saving another users city during onboarding', function () {
    $user = onboardingUser();
    $otherUser = onboardingUser(['email' => fake()->unique()->safeEmail()]);
    $otherCity = app(UserCityService::class)->findOrCreate($otherUser, 'BR', 'RS', 'Capão da Canoa');

    $preferences = app(UserPreferencesService::class);

    expect(fn () => $preferences->completeOnboarding($user, onboardingCompletionData([
        'default_city_id' => $otherCity->id,
    ])))->toThrow(ValidationException::class);

    expect($user->fresh()->default_city_id)->toBeNull();
});
