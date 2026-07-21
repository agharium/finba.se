<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\People\PersonResource;
use App\Models\User;
use App\Services\LocationDefaultsService;
use App\Services\UserPreferencesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    fakeGeoContractApi();
});

function onboardingUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Onboarding User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['locale' => 'pt_BR'],
        'locale' => 'pt_BR',
        'onboarding_completed_at' => null,
    ], $overrides));
}

function onboardingCompletionData(array $overrides = []): array
{
    return array_merge([
        'locale' => 'pt_BR',
        'geo_country_code' => 'BR',
        'geo_region_id' => 2021,
        'geo_city_id' => 1001,
        'advanced' => false,
        'tither' => false,
        'accounts_receivable' => false,
    ], $overrides);
}

function submitOnboardingWizard($component, User $user, array $overrides = [])
{
    $data = onboardingCompletionData($overrides);

    $component
        ->mountAction('onboarding')
        ->set('mountedActions.0.data.locale', $data['locale'])
        ->set('mountedActions.0.data.geo_country_code', $data['geo_country_code'])
        ->set('mountedActions.0.data.geo_region_id', $data['geo_region_id'])
        ->set('mountedActions.0.data.geo_city_id', $data['geo_city_id'])
        ->goToNextWizardStep()
        ->set('mountedActions.0.data.advanced', $data['advanced'])
        ->set('mountedActions.0.data.accounts_receivable', $data['accounts_receivable'])
        ->set('mountedActions.0.data.tither', $data['tither'])
        ->goToNextWizardStep();

    return $component->callMountedAction();
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

it('stores BR internally when onboarding locale is pt_BR', function () {
    $user = onboardingUser();

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
    )->assertRedirect(Dashboard::getUrl());

    expect(app(LocationDefaultsService::class)->internalCountryCode($user->fresh()))->toBe('BR');
});

it('persists external geo city id from onboarding', function () {
    $user = onboardingUser();

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
        ['geo_city_id' => 1001],
    )->assertRedirect(Dashboard::getUrl());

    $fresh = $user->fresh();

    expect($fresh->geo_city_id)->toBe(1001)
        ->and(app(LocationDefaultsService::class)->regionCode($fresh))->toBe('RS');
});

it('persists the same external geo city id when selected again', function () {
    $user = onboardingUser(['geo_city_id' => 1001]);

    submitOnboardingWizard(
        Livewire::actingAs($user)->test(Dashboard::class),
        $user,
        ['geo_city_id' => 1001],
    )->assertRedirect(Dashboard::getUrl());

    expect($user->fresh()->geo_city_id)->toBe(1001);
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

    $preferences->completeOnboarding($user, onboardingCompletionData([
        'advanced' => true,
        'accounts_receivable' => true,
        'tither' => true,
        'geo_city_id' => 1001,
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

it('assigns external geo city id per user during onboarding', function () {
    $user = onboardingUser();
    $otherUser = onboardingUser(['email' => fake()->unique()->safeEmail()]);
    $otherUser->update(['geo_city_id' => 1002]);

    app(UserPreferencesService::class)->completeOnboarding($user, onboardingCompletionData([
        'geo_city_id' => 1001,
    ]));

    $fresh = $user->fresh();

    expect($fresh->geo_city_id)->toBe(1001)
        ->and($fresh->geo_city_id)->not->toBe($otherUser->fresh()->geo_city_id);
});
