<?php

use App\Enums\IncomePaymentMode;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function transactionServiceUser(array $settings = []): User
{
    return User::query()->create([
        'name' => 'Transaction User',
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

function transactionServicePerson(User $user, array $attributes = []): Person
{
    return Person::query()->create(array_merge([
        'name' => fake()->name(),
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ], $attributes));
}

it('creates a normal expense through TransactionService', function () {
    $user = transactionServiceUser();

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'amount' => 75.25,
        'description' => 'Despesa comum',
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->isReceivableSale)->toBeFalse()
        ->and($result->record)->toBeInstanceOf(Transaction::class)
        ->and($result->record->type)->toBe(TransactionType::EXPENSE)
        ->and($result->record->amount)->toBe('75.25')
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Loan::query()->count())->toBe(0);
});

it('creates a normal income through TransactionService', function () {
    $user = transactionServiceUser();

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::NOW->value,
        'amount' => 120,
        'description' => 'Receita imediata',
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->isReceivableSale)->toBeFalse()
        ->and($result->record)->toBeInstanceOf(Transaction::class)
        ->and($result->record->amount)->toBe('120.00')
        ->and(Transaction::query()->count())->toBe(1);
});

it('delegates receivable sales to ReceivableSaleService', function () {
    $user = transactionServiceUser();
    $person = transactionServicePerson($user);

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
        'amount' => 300,
        'description' => 'Venda parcelada',
        'person_id' => $person->id,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->isReceivableSale)->toBeTrue()
        ->and($result->record)->toBeInstanceOf(Loan::class)
        ->and($result->record->type)->toBe(LoanType::RECEIVABLE)
        ->and($result->record->status)->toBe(LoanStatus::OPEN)
        ->and($result->record->person_id)->toBe($person->id)
        ->and(Loan::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0);
});

it('does not create receivable sales for users without the feature flag', function () {
    $user = transactionServiceUser(['accounts_receivable' => false]);
    $person = transactionServicePerson($user);

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
        'amount' => 300,
        'person_id' => $person->id,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->isReceivableSale)->toBeFalse()
        ->and($result->record)->toBeInstanceOf(Transaction::class)
        ->and(Loan::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(1);
});

it('detects receivable sale only for enabled income later flow', function () {
    $user = transactionServiceUser();
    $service = app(TransactionService::class);

    expect($service->isReceivableSale($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
    ]))->toBeTrue()
        ->and($service->isReceivableSale($user, [
            'type' => TransactionType::INCOME->value,
            'payment_mode' => IncomePaymentMode::NOW->value,
        ]))->toBeFalse()
        ->and($service->isReceivableSale($user, [
            'type' => TransactionType::EXPENSE->value,
            'payment_mode' => IncomePaymentMode::LATER->value,
        ]))->toBeFalse();

    $disabledUser = transactionServiceUser(['accounts_receivable' => false]);

    expect($service->isReceivableSale($disabledUser, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
    ]))->toBeFalse();
});

it('delegates receivable payments through ReceivablePaymentService', function () {
    $user = transactionServiceUser();
    $person = transactionServicePerson($user);

    $receivable = Loan::query()->create([
        'user_id' => $user->id,
        'person_id' => $person->id,
        'type' => LoanType::RECEIVABLE,
        'status' => LoanStatus::OPEN,
        'original_amount' => 500,
        'description' => 'Venda a prazo',
    ]);

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::NOW->value,
        'amount' => 200,
        'description' => 'Recebimento parcial',
        'loan_id' => $receivable->id,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->isReceivableSale)->toBeFalse()
        ->and($result->record)->toBeInstanceOf(Transaction::class)
        ->and($result->record->loan_id)->toBe($receivable->id)
        ->and($result->record->amount)->toBe('200.00')
        ->and($receivable->fresh()->status)->toBe(LoanStatus::OPEN)
        ->and(Transaction::query()->count())->toBe(1);
});

it('assigns user_id when creating through TransactionService', function () {
    $user = transactionServiceUser();

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::NOW->value,
        'amount' => 40,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ]);

    expect($result->record->user_id)->toBe($user->id);
});
