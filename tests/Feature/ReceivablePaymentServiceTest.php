<?php

use App\Enums\IncomePaymentMode;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\ReceivablePaymentException;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function receivablePaymentUser(): User
{
    return User::query()->create([
        'name' => 'Receivable Payment User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
        ],
    ]);
}

function receivablePaymentPerson(User $user): Person
{
    return Person::query()->create([
        'name' => fake()->name(),
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ]);
}

function createOpenReceivable(User $user, Person $person, float $originalAmount): Loan
{
    return Loan::query()->create([
        'user_id' => $user->id,
        'person_id' => $person->id,
        'type' => LoanType::RECEIVABLE,
        'status' => LoanStatus::OPEN,
        'original_amount' => $originalAmount,
        'description' => 'Venda a prazo',
    ]);
}

function payReceivable(User $user, Loan $receivable, float $amount): Transaction
{
    return app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::NOW->value,
        'amount' => $amount,
        'description' => 'Recebimento',
        'loan_id' => $receivable->id,
        'status' => 'PAID',
        'date' => now()->toDateString(),
    ])->record;
}

it('keeps receivable open after a partial payment', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    payReceivable($user, $receivable, 200);

    expect($receivable->fresh()->status)->toBe(LoanStatus::OPEN);
});

it('closes receivable after exact full payment', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    payReceivable($user, $receivable, 500);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED)
        ->and(Transaction::query()->where('loan_id', $receivable->id)->count())->toBe(1);
});

it('blocks overpayment against remaining balance', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    payReceivable($user, $receivable, 200);

    expect(fn () => payReceivable($user, $receivable, 301))
        ->toThrow(ReceivablePaymentException::class, 'O valor informado excede o saldo em aberto da conta a receber.');

    expect($receivable->fresh()->status)->toBe(LoanStatus::OPEN)
        ->and(Transaction::query()->where('loan_id', $receivable->id)->count())->toBe(1);
});

it('closes receivable after multiple partial payments', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    payReceivable($user, $receivable, 150);
    payReceivable($user, $receivable, 200);

    expect($receivable->fresh()->status)->toBe(LoanStatus::OPEN);

    payReceivable($user, $receivable, 150);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED)
        ->and(Transaction::query()->where('loan_id', $receivable->id)->count())->toBe(3);
});

it('allows exact remaining balance after prior partial payments', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    payReceivable($user, $receivable, 200);
    payReceivable($user, $receivable, 300);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED);
});

it('ignores non-paid income when calculating remaining balance', function () {
    $user = receivablePaymentUser();
    $person = receivablePaymentPerson($user);
    $receivable = createOpenReceivable($user, $person, 500);

    Transaction::query()->create([
        'user_id' => $user->id,
        'type' => TransactionType::INCOME->value,
        'amount' => 400,
        'loan_id' => $receivable->id,
        'status' => 'PENDING',
        'date' => now(),
    ]);

    payReceivable($user, $receivable, 500);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED);
});
