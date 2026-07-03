<?php

use App\Enums\IncomePaymentMode;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\ReceivableSaleException;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReceivableSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function createReceivableUser(array $settings = []): User
{
    return User::query()->create([
        'name' => 'Receivable User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => array_merge([
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
        ], $settings),
    ]);
}

function receivableFormData(User $user, Person $person, array $overrides = []): array
{
    return array_merge([
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
        'amount' => 250.50,
        'description' => 'Venda de produtos',
        'person_id' => $person->id,
        'user_id' => $user->id,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ], $overrides);
}

function createPerson(User $user, array $attributes = []): Person
{
    return Person::query()->create(array_merge([
        'name' => fake()->name(),
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ], $attributes));
}

it('creates a receivable loan when payment mode is later', function () {
    $user = createReceivableUser();
    $person = createPerson($user);

    $loan = app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person),
    );

    expect($loan)->toBeInstanceOf(Loan::class)
        ->and($loan->type)->toBe(LoanType::RECEIVABLE)
        ->and($loan->status)->toBe(LoanStatus::OPEN)
        ->and($loan->original_amount)->toBe('250.50')
        ->and($loan->person_id)->toBe($person->id)
        ->and($loan->user_id)->toBe($user->id)
        ->and($loan->description)->toBe('Venda de produtos');

    expect(Loan::query()->count())->toBe(1);
});

it('does not create a transaction for receivable sales', function () {
    $user = createReceivableUser();
    $person = createPerson($user);

    app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person),
    );

    expect(Transaction::query()->count())->toBe(0);
});

it('uses default description when receivable sale has no description', function () {
    $user = createReceivableUser();
    $person = createPerson($user);

    $loan = app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person, ['description' => null]),
    );

    expect($loan->description)->toBe('Venda a prazo');
});

it('still creates a transaction for immediate income', function () {
    $user = createReceivableUser();

    $transaction = Transaction::query()->create(
        TransactionResource::prepareTransactionAttributes([
            'type' => TransactionType::INCOME->value,
            'payment_mode' => IncomePaymentMode::NOW->value,
            'amount' => 180,
            'description' => 'Receita imediata',
            'user_id' => $user->id,
            'status' => 'PAID',
            'date' => now()->toDateString(),
        ]),
    );

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->amount)->toBe('180.00')
        ->and(Loan::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(1);
});

it('persists person_id on receivable loans', function () {
    $user = createReceivableUser();
    $person = createPerson($user, ['name' => 'Cliente A']);

    app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person),
    );

    $loan = Loan::query()->first();

    expect($loan?->person_id)->toBe($person->id)
        ->and($loan?->person?->name)->toBe('Cliente A');
});

it('stores receivable value in original_amount', function () {
    $user = createReceivableUser();
    $person = createPerson($user);

    app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person, ['amount' => 999.99]),
    );

    expect(Loan::query()->value('original_amount'))->toBe('999.99');
});

it('blocks receivable sales for users without accounts receivable', function () {
    $user = User::query()->create([
        'name' => 'Basic User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => false,
        ],
    ]);
    $person = createPerson($user);

    expect(fn () => app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person),
    ))->toThrow(ReceivableSaleException::class, 'Vendas a prazo não estão habilitadas para este usuário.');

    expect(Loan::query()->count())->toBe(0);
});

it('requires a person for receivable sales', function () {
    $user = createReceivableUser();

    expect(fn () => app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, createPerson($user), ['person_id' => null]),
    ))->toThrow(ReceivableSaleException::class, 'Selecione uma pessoa para criar a conta a receber.');
});

it('requires a positive amount for receivable sales', function () {
    $user = createReceivableUser();
    $person = createPerson($user);

    expect(fn () => app(ReceivableSaleService::class)->create(
        $user,
        receivableFormData($user, $person, ['amount' => 0]),
    ))->toThrow(ReceivableSaleException::class, 'Informe um valor válido para a venda a prazo.');
});

it('detects receivable branch only for enabled income later flow', function () {
    $user = createReceivableUser();
    Auth::login($user);

    expect(TransactionResource::shouldCreateReceivable([
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
    ]))->toBeTrue()
        ->and(TransactionResource::shouldCreateReceivable([
            'type' => TransactionType::INCOME->value,
            'payment_mode' => IncomePaymentMode::NOW->value,
        ]))->toBeFalse()
        ->and(TransactionResource::shouldCreateReceivable([
            'type' => TransactionType::EXPENSE->value,
            'payment_mode' => IncomePaymentMode::LATER->value,
        ]))->toBeFalse();

    Auth::login(User::query()->create([
        'name' => 'Disabled User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => [
            'advanced' => false,
            'tither' => false,
            'accounts_receivable' => false,
        ],
    ]));

    expect(TransactionResource::shouldCreateReceivable([
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
    ]))->toBeFalse();
});

it('strips payment_mode before persisting transactions', function () {
    $attributes = TransactionResource::prepareTransactionAttributes([
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::NOW->value,
        'amount' => 50,
        'user_id' => 'user-id',
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($attributes)->not->toHaveKey('payment_mode');
});
