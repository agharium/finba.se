<?php

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Exceptions\ReceivablePaymentException;
use App\Filament\Resources\Loans\LoanResource;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReceivablePaymentService;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loanResourceUser(): User
{
    return User::query()->create([
        'name' => 'Loan Resource User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'default_country_code' => 'BR',
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
            'locale' => 'pt-BR',
        ],
    ]);
}

function loanResourcePerson(User $user, string $name): Person
{
    return Person::query()->create([
        'name' => $name,
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ]);
}

function loanResourceReceivable(User $user, Person $person, float $amount, ?string $description = null): Loan
{
    return Loan::query()->create([
        'user_id' => $user->id,
        'person_id' => $person->id,
        'type' => LoanType::RECEIVABLE,
        'status' => LoanStatus::OPEN,
        'original_amount' => $amount,
        'description' => $description ?? 'Venda a prazo',
    ]);
}

it('displays remaining balance on loan cards', function () {
    $user = loanResourceUser();
    $person = loanResourcePerson($user, 'Maria');
    $receivable = loanResourceReceivable($user, $person, 400, 'Produtos diversos');

    Transaction::query()->create([
        'user_id' => $user->id,
        'type' => 'INCOME',
        'amount' => 150,
        'loan_id' => $receivable->id,
        'status' => 'PAID',
        'date' => now(),
    ]);

    $card = LoanResource::mobileCardData($receivable->fresh(['person']));

    expect($card['description'])->toBe('Produtos diversos')
        ->and($card['customer'])->toBe('Maria')
        ->and($card['original'])->toBe(MoneyFormatter::format(400, $user))
        ->and($card['received'])->toBe(MoneyFormatter::format(150, $user))
        ->and($card['remaining'])->toBe(MoneyFormatter::format(250, $user))
        ->and($card['is_open'])->toBeTrue();
});

it('registers partial payment and keeps receivable open', function () {
    $user = loanResourceUser();
    $person = loanResourcePerson($user, 'João');
    $receivable = loanResourceReceivable($user, $person, 300);

    app(ReceivablePaymentService::class)->registerPayment($user, $receivable, [
        'amount' => 100,
        'date' => now()->toDateString(),
    ]);

    expect($receivable->fresh()->status)->toBe(LoanStatus::OPEN)
        ->and(Transaction::query()->where('loan_id', $receivable->id)->count())->toBe(1);
});

it('registers full payment and closes receivable automatically', function () {
    $user = loanResourceUser();
    $person = loanResourcePerson($user, 'Ana');
    $receivable = loanResourceReceivable($user, $person, 250);

    app(ReceivablePaymentService::class)->registerPayment($user, $receivable, [
        'amount' => 250,
        'date' => now()->toDateString(),
        'description' => 'Quitação',
    ]);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED);
});

it('blocks overpayment when registering payment', function () {
    $user = loanResourceUser();
    $person = loanResourcePerson($user, 'Carlos');
    $receivable = loanResourceReceivable($user, $person, 200);

    app(ReceivablePaymentService::class)->registerPayment($user, $receivable, [
        'amount' => 50,
        'date' => now()->toDateString(),
    ]);

    expect(fn () => app(ReceivablePaymentService::class)->registerPayment($user, $receivable, [
        'amount' => 151,
        'date' => now()->toDateString(),
    ]))->toThrow(ReceivablePaymentException::class, 'O valor informado excede o saldo em aberto da conta a receber.');

    expect($receivable->fresh()->status)->toBe(LoanStatus::OPEN);
});

it('closes receivable after multiple partial payments from loan resource flow', function () {
    $user = loanResourceUser();
    $person = loanResourcePerson($user, 'Pedro');
    $receivable = loanResourceReceivable($user, $person, 600);
    $service = app(ReceivablePaymentService::class);

    $service->registerPayment($user, $receivable, ['amount' => 200, 'date' => now()->toDateString()]);
    $service->registerPayment($user, $receivable->fresh(), ['amount' => 250, 'date' => now()->toDateString()]);
    $service->registerPayment($user, $receivable->fresh(), ['amount' => 150, 'date' => now()->toDateString()]);

    expect($receivable->fresh()->status)->toBe(LoanStatus::CLOSED)
        ->and(Transaction::query()->where('loan_id', $receivable->id)->count())->toBe(3);
});

it('tracks balances independently for multiple customers', function () {
    $user = loanResourceUser();
    $maria = loanResourcePerson($user, 'Maria');
    $joao = loanResourcePerson($user, 'João');

    $mariaReceivable = loanResourceReceivable($user, $maria, 500, 'Conta Maria');
    $joaoReceivable = loanResourceReceivable($user, $joao, 300, 'Conta João');

    app(ReceivablePaymentService::class)->registerPayment($user, $mariaReceivable, [
        'amount' => 200,
        'date' => now()->toDateString(),
    ]);

    expect(LoanResource::mobileCardData($mariaReceivable->fresh(['person']))['remaining'])->toBe(MoneyFormatter::format(300, $user))
        ->and(LoanResource::mobileCardData($joaoReceivable->fresh(['person']))['remaining'])->toBe(MoneyFormatter::format(300, $user))
        ->and($mariaReceivable->fresh()->status)->toBe(LoanStatus::OPEN)
        ->and($joaoReceivable->fresh()->status)->toBe(LoanStatus::OPEN);
});

it('defaults loan resource query to the authenticated user', function () {
    $user = loanResourceUser();
    $otherUser = loanResourceUser();
    $person = loanResourcePerson($user, 'Cliente');

    loanResourceReceivable($user, $person, 100);
    loanResourceReceivable($otherUser, loanResourcePerson($otherUser, 'Outro'), 100);

    $this->actingAs($user);

    expect(LoanResource::getEloquentQuery()->count())->toBe(1);
});
