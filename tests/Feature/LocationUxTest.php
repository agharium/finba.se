<?php

use App\Filament\Forms\LocationFormFields;
use App\Filament\Pages\Profile;
use App\Filament\Resources\People\PersonResource;
use App\Models\City;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LocationCatalogService;
use App\Services\LocationDefaultsService;
use App\Services\UserCityService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function locationUxUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Location UX User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['advanced' => true, 'locale' => 'pt-BR'],
        'default_country_code' => 'BR',
        'default_region_code' => 'RS',
    ], $overrides));
}

it('initializes internal country as BR for pt-BR when absent', function () {
    $user = locationUxUser([
        'default_country_code' => null,
        'default_region_code' => null,
        'settings' => ['advanced' => true, 'locale' => 'pt-BR'],
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->assertSet('data.default_country_code', 'BR');
});

it('does not render país in profile location fields', function () {
    $components = [
        LocationFormFields::profileLocaleSelect(),
        LocationFormFields::profileHiddenCountryField(),
        LocationFormFields::profileRegionSelect(),
        LocationFormFields::profileDefaultCitySelect(),
    ];

    $labels = collect($components)
        ->map(fn ($component) => $component->getLabel())
        ->filter()
        ->all();

    expect($labels)->not->toContain('País');
});

it('does not include geolocation in compact location picker', function () {
    $names = collect(LocationFormFields::compactLocationPicker('city_id'))
        ->map(fn ($component) => $component->getName())
        ->all();

    expect($names)->not->toContain('location_geolocation_shortcut')
        ->and($names)->not->toContain('geolocation_suggest');
});

it('saves state and city manually from profile', function () {
    $user = locationUxUser([
        'default_country_code' => null,
        'default_region_code' => null,
        'default_city_id' => null,
    ]);

    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.locale', 'pt-BR')
        ->set('data.default_region_code', 'RS')
        ->set('data.default_city_id', $city->id)
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    $fresh = $user->fresh();

    expect($fresh->default_country_code)->toBe('BR')
        ->and($fresh->default_region_code)->toBe('RS')
        ->and($fresh->default_city_id)->toBe($city->id);
});

it('resets city when profile state changes', function () {
    $user = locationUxUser();
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.default_city_id', $city->id)
        ->set('data.default_region_code', 'SP')
        ->assertSet('data.default_city_id', null);
});

it('does not overwrite an existing saved country when locale changes', function () {
    $user = locationUxUser([
        'default_country_code' => 'BR',
        'settings' => ['advanced' => true, 'locale' => 'pt-BR'],
    ]);

    Livewire::actingAs($user)
        ->test(Profile::class)
        ->set('data.locale', 'en')
        ->set('data.default_region_code', 'RS')
        ->call('save')
        ->assertRedirect(Profile::getUrl());

    expect($user->fresh()->default_country_code)->toBe('BR');
});

it('leaves country unset for english locale without a saved country', function () {
    expect(app(LocationDefaultsService::class)->countryFromLocale('en'))->toBeNull();
});

it('loads region options based on internal country', function () {
    $regions = app(LocationCatalogService::class)->regionOptions('BR');

    expect($regions)->toHaveKey('RS')
        ->and(app(LocationCatalogService::class)->regionOptions('US'))->not->toHaveKey('RS');
});

it('loads city options based on default region', function () {
    $user = locationUxUser();
    $options = app(UserCityService::class)->citySelectOptions($user, 'BR', 'RS', 'Porto');

    expect($options)->not->toBeEmpty();
});

it('resolves catalog city labels without querying invalid uuids', function () {
    $label = app(UserCityService::class)->optionLabel(
        UserCityService::catalogOptionKey('Tramandaí'),
        'RS',
    );

    expect($label)->toBe('Tramandaí');
});

it('builds compact location picker without visible country fields', function () {
    $names = collect(LocationFormFields::compactLocationPicker('city_id'))
        ->map(fn ($component) => $component->getName())
        ->all();

    expect($names)->toContain('city_id')
        ->and($names)->toContain('search_other_region')
        ->and($names)->toContain('temporary_region_code')
        ->and($names)->not->toContain('temporary_country_code');
});

it('keeps existing transaction city on edit', function () {
    $user = locationUxUser(['default_city_id' => null]);
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Osório');
    $other = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'EXPENSE',
        'amount' => 25,
        'status' => 'PAID',
        'date' => now(),
        'city_id' => $city->id,
    ]);

    $user->update(['default_city_id' => $other->id]);

    $transaction->update(['description' => 'Updated']);

    expect($transaction->fresh()->city_id)->toBe($city->id);
});

it('uses default city for transaction create when configured', function () {
    $user = locationUxUser();
    $defaultCity = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');
    $user->update(['default_city_id' => $defaultCity->id]);

    $this->actingAs($user->fresh());

    expect(LocationFormFields::userDefaultCityId())->toBe($defaultCity->id);
});

it('does not overwrite user defaults when stripping ephemeral override fields', function () {
    $user = locationUxUser();
    $originalRegion = $user->default_region_code;

    $data = LocationFormFields::stripEphemeralFields([
        'city_id' => null,
        'search_other_region' => true,
        'temporary_region_code' => 'SP',
    ]);

    expect($data)->not->toHaveKey('temporary_region_code')
        ->and($user->fresh()->default_region_code)->toBe($originalRegion);
});

it('reports missing defaults through location defaults service', function () {
    $user = locationUxUser([
        'default_country_code' => null,
        'default_region_code' => null,
    ]);

    expect(app(LocationDefaultsService::class)->hasConfiguredLocation($user))->toBeFalse();
});

it('keeps frequent city ranking scoped to the authenticated user', function () {
    $owner = locationUxUser();
    $other = locationUxUser(['email' => fake()->unique()->safeEmail()]);

    $city = app(UserCityService::class)->findOrCreate($owner, 'BR', 'RS', 'Capão da Canoa');
    $city->forceFill(['usage_count' => 8, 'last_used_at' => now()])->save();

    $ownerOptions = app(UserCityService::class)->citySelectOptions($owner, 'BR', 'RS');
    $otherOptions = app(UserCityService::class)->citySelectOptions($other, 'BR', 'RS');

    expect($ownerOptions)->toHaveKey('Mais usadas')
        ->and(collect($otherOptions)->flatten(1)->keys()->all())->not->toContain($city->id);
});

it('creates personal cities organically through person save', function () {
    $user = locationUxUser();
    $this->actingAs($user);

    $person = PersonResource::savePerson(new Person([
        'user_id' => $user->id,
        'name' => 'Cliente',
        'types' => ['CONTACT'],
    ]), [
        'user_id' => $user->id,
        'name' => 'Cliente',
        'types' => ['CONTACT'],
        'categories' => [],
        'cities' => [UserCityService::catalogOptionKey('Imbé')],
    ]);

    $city = City::query()->where('user_id', $user->id)->where('name', 'Imbé')->first();

    expect($city)->not->toBeNull()
        ->and($person->cities()->pluck('cities.id')->all())->toContain($city->id);
});

it('does not include Cidades in navigation', function () {
    $user = locationUxUser();
    $this->actingAs($user);

    $navigationLabels = collect(Filament::getNavigation())
        ->map(fn ($item): string => (string) $item->getLabel())
        ->all();

    expect($navigationLabels)->not->toContain('Cidades');
});
