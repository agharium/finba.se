<?php

use App\Enums\LoanType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createUser(array $settings = []): User
{
    return User::query()->create([
        'name' => 'Test User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => array_merge([
            'advanced' => false,
            'tither' => false,
            'accounts_receivable' => false,
        ], $settings),
    ]);
}

it('defaults accounts receivable setting to false', function () {
    $user = createUser();

    expect($user->hasSetting('accounts_receivable'))->toBeFalse()
        ->and($user->usesAccountsReceivable())->toBeFalse();
});

it('allows enabling accounts receivable only for advanced users', function () {
    $user = createUser(['advanced' => true]);

    $user->update([
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
        ],
    ]);

    expect($user->fresh()->usesAccountsReceivable())->toBeTrue();
});

it('clears accounts receivable when advanced mode is disabled', function () {
    $user = createUser([
        'advanced' => true,
        'accounts_receivable' => true,
    ]);

    $user->update([
        'settings' => [
            'advanced' => false,
            'tither' => false,
            'accounts_receivable' => true,
        ],
    ]);

    expect($user->fresh()->hasSetting('accounts_receivable'))->toBeFalse()
        ->and($user->fresh()->usesAccountsReceivable())->toBeFalse();
});

it('exposes user helper methods from settings', function () {
    $user = createUser([
        'advanced' => true,
        'tither' => true,
        'accounts_receivable' => true,
    ]);

    expect($user->hasAdvancedMode())->toBeTrue()
        ->and($user->isTither())->toBeTrue()
        ->and($user->usesAccountsReceivable())->toBeTrue()
        ->and($user->hasSetting('advanced'))->toBeTrue();
});

it('includes receivable in loan type enum labels', function () {
    expect(LoanType::RECEIVABLE->getLabel())->toBe('Conta a receber')
        ->and(LoanType::LENT->getLabel())->toBe('Empréstimo concedido')
        ->and(LoanType::BORROWED->getLabel())->toBe('Dívida');
});

it('persists settings json for profile feature flags', function () {
    $user = createUser([
        'advanced' => true,
        'tither' => true,
        'accounts_receivable' => true,
    ]);

    expect($user->fresh()->settings)->toMatchArray([
        'advanced' => true,
        'tither' => true,
        'accounts_receivable' => true,
    ]);
});
