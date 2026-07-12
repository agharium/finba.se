<?php

use App\Models\City;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CityUsageService;
use App\Services\UserCityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cityLocationUser(): User
{
    return User::query()->create([
        'name' => 'City Location User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => ['advanced' => true],
    ]);
}

it('creates one personal city when selecting a catalog city', function () {
    $user = cityLocationUser();
    $service = app(UserCityService::class);

    $city = $service->findOrCreate($user, 'BR', 'RS', 'Porto Alegre');

    expect($city)->toBeInstanceOf(City::class)
        ->and($city->name)->toBe('Porto Alegre')
        ->and(City::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('reuses the same personal city when selected again', function () {
    $user = cityLocationUser();
    $service = app(UserCityService::class);

    $first = $service->findOrCreate($user, 'BR', 'RS', 'Porto Alegre');
    $second = $service->findOrCreate($user, 'BR', 'RS', 'Porto Alegre');

    expect($first->id)->toBe($second->id)
        ->and(City::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('does not create duplicates for capitalization and whitespace differences', function () {
    $user = cityLocationUser();
    $service = app(UserCityService::class);

    $first = $service->findOrCreate($user, 'BR', 'RS', '  porto   alegre ');
    $second = $service->findOrCreate($user, 'BR', 'RS', 'PORTO ALEGRE');

    expect($first->id)->toBe($second->id)
        ->and($first->name)->toBe('Porto Alegre')
        ->and(City::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('scopes personal cities by user', function () {
    $firstUser = cityLocationUser();
    $secondUser = cityLocationUser();
    $service = app(UserCityService::class);

    $service->findOrCreate($firstUser, 'BR', 'RS', 'Porto Alegre');
    $service->findOrCreate($secondUser, 'BR', 'RS', 'Porto Alegre');

    expect(City::query()->where('user_id', $firstUser->id)->count())->toBe(1)
        ->and(City::query()->where('user_id', $secondUser->id)->count())->toBe(1);
});

it('ranks frequently used cities ahead of catalog results', function () {
    $user = cityLocationUser();
    $service = app(UserCityService::class);

    $used = $service->findOrCreate($user, 'BR', 'RS', 'Tramandaí');
    $used->forceFill(['usage_count' => 5, 'last_used_at' => now()])->save();

    $options = $service->citySelectOptions($user, 'BR', 'RS');

    expect($options)->toHaveKey('Mais usadas')
        ->and(array_key_first($options['Mais usadas']))->toBe($used->id);
});

it('increments usage when a transaction is saved with a city', function () {
    $user = cityLocationUser();
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Osório');

    Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'EXPENSE',
        'amount' => 10,
        'status' => 'PAID',
        'date' => now(),
        'city_id' => $city->id,
    ]);

    expect($city->fresh()->usage_count)->toBe(1)
        ->and($city->fresh()->last_used_at)->not->toBeNull();
});

it('does not increment usage when editing a transaction without changing city', function () {
    $user = cityLocationUser();
    $city = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Osório');

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'EXPENSE',
        'amount' => 10,
        'status' => 'PAID',
        'date' => now(),
        'city_id' => $city->id,
    ]);

    $city->refresh();
    $startingCount = $city->usage_count;

    $transaction->update(['description' => 'Updated description']);

    expect($city->fresh()->usage_count)->toBe($startingCount);
});

it('records only newly attached person cities', function () {
    $user = cityLocationUser();
    $first = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Porto Alegre');
    $second = app(UserCityService::class)->findOrCreate($user, 'BR', 'RS', 'Tramandaí');

    app(CityUsageService::class)->recordNewlyAttached([$first->id, $second->id], [$first->id]);

    expect($first->fresh()->usage_count)->toBe(0)
        ->and($second->fresh()->usage_count)->toBe(1);
});

it('does not expose another users frequent cities', function () {
    $owner = cityLocationUser();
    $other = cityLocationUser();
    $service = app(UserCityService::class);

    $city = $service->findOrCreate($owner, 'BR', 'RS', 'Capão da Canoa');
    $city->forceFill(['usage_count' => 9, 'last_used_at' => now()])->save();

    $options = $service->citySelectOptions($other, 'BR', 'RS');

    expect(collect($options)->flatten(1)->keys()->all())->not->toContain($city->id);
});
