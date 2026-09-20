<?php

use App\Enums\CommitmentType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\TransactionType;
use App\Exceptions\CommitmentAllocationException;
use App\Exceptions\LoanPaymentException;
use App\Models\Commitment;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CommitmentAllocationService;
use App\Services\LoanBalanceService;
use App\Services\LoanCommitmentRecalculationService;
use App\Services\LoanCommitmentScheduleService;
use App\Services\LoanOriginationService;
use App\Services\LoanPaymentService;
use App\Support\InstallmentDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    prepareGeoTestEnvironment();
    fakeGeoContractApi();
});

function commitmentDomainUser(): User
{
    return User::query()->create([
        'name' => 'Commitment Domain User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => [
            'advanced' => true,
            'tither' => false,
            'accounts_receivable' => true,
            'locale' => 'pt_BR',
        ],
        'locale' => 'pt_BR',
    ]);
}

function commitmentDomainPerson(User $user, string $name = 'João'): Person
{
    return Person::query()->create([
        'name' => $name,
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ]);
}

it('creates a LENT loan with initial expense transaction and expected commitments', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 600,
        'person_id' => $person->id,
        'description' => 'Empréstimo ao João',
        'date' => '2026-01-01',
        'installments_count' => 6,
        'first_due_date' => '2026-02-01',
    ]);

    expect($result->loan->type)->toBe(LoanType::LENT)
        ->and($result->transaction->type)->toBe(TransactionType::EXPENSE)
        ->and($result->transaction->amount)->toBe('600.00')
        ->and($result->transaction->loan_id)->toBe($result->loan->id)
        ->and($result->commitments)->toHaveCount(6)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(Commitment::query()->count())->toBe(6)
        ->and($result->commitments->sum(fn (Commitment $c) => (float) $c->amount))->toEqual(600.0);

    $result->commitments->each(function (Commitment $commitment): void {
        expect($commitment->type)->toBe(CommitmentType::LOAN)
            ->and($commitment->isSatisfied())->toBeFalse()
            ->and($commitment->transactions()->count())->toBe(0);
    });
});

it('creates a BORROWED loan with initial income transaction', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::BORROWED,
        'original_amount' => 2000,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 2,
        'first_due_date' => '2026-02-01',
    ]);

    expect($result->transaction->type)->toBe(TransactionType::INCOME)
        ->and($result->loan->type)->toBe(LoanType::BORROWED)
        ->and($result->commitments)->toHaveCount(2);
});

it('does not create a transaction merely because a commitment exists', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $loan = Loan::query()->create([
        'user_id' => $user->id,
        'person_id' => $person->id,
        'type' => LoanType::LENT,
        'status' => LoanStatus::OPEN,
        'original_amount' => 100,
        'description' => 'Sem origem',
    ]);

    app(LoanCommitmentScheduleService::class)->generate($loan, 1, '2026-03-01', 100);

    expect(Commitment::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(app(LoanBalanceService::class)->remainingBalance($loan))->toBe('100.00');
});

it('registerPayment creates the actual transaction without allocating commitments', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 300,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 3,
        'first_due_date' => '2026-02-01',
    ]);

    $commitmentCountBefore = Commitment::query()->count();
    $sequencesBefore = Commitment::query()->orderBy('sequence')->pluck('amount', 'sequence')->all();

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 100,
        'date' => '2026-02-01',
    ]);

    expect($payment->transaction->type)->toBe(TransactionType::INCOME)
        ->and($payment->transaction->amount)->toBe('100.00')
        ->and($payment->allocations)->toHaveCount(0)
        ->and($payment->unallocatedAmount)->toBe('100.00')
        ->and($payment->transaction->commitments()->count())->toBe(0)
        ->and(Commitment::query()->count())->toBe($commitmentCountBefore)
        ->and(Commitment::query()->orderBy('sequence')->pluck('amount', 'sequence')->all())->toBe($sequencesBefore)
        ->and(app(LoanBalanceService::class)->remainingBalance($result->loan->fresh()))->toBe('200.00');
});

it('records BORROWED repayment as expense without automatic allocation', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::BORROWED,
        'original_amount' => 200,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 2,
        'first_due_date' => '2026-02-01',
    ]);

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 100,
        'date' => '2026-02-01',
    ]);

    expect($payment->transaction->type)->toBe(TransactionType::EXPENSE)
        ->and($payment->allocations)->toHaveCount(0)
        ->and($result->commitments->first()->fresh()->allocatedAmount())->toBe('0.00')
        ->and(app(LoanBalanceService::class)->remainingBalance($result->loan->fresh()))->toBe('100.00');
});

it('explicitly allocates only to the selected commitment', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 300,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 3,
        'first_due_date' => '2026-02-01',
    ]);

    $first = $result->commitments->sortBy('sequence')->first();
    $second = $result->commitments->sortBy('sequence')->values()->get(1);

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 80,
        'date' => '2026-02-01',
        'commitment_id' => $first->id,
    ]);

    expect($payment->allocations)->toHaveCount(1)
        ->and($payment->allocations->first()['amount'])->toBe('80.00')
        ->and($first->fresh()->allocatedAmount())->toBe('80.00')
        ->and($first->fresh()->isPartiallyPaid())->toBeTrue()
        ->and($second->fresh()->allocatedAmount())->toBe('0.00')
        ->and($payment->unallocatedAmount)->toBe('0.00');
});

it('keeps excess unallocated when explicit commitment cannot absorb the full payment', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 300,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 3,
        'first_due_date' => '2026-02-01',
    ]);

    $first = $result->commitments->sortBy('sequence')->first();
    $second = $result->commitments->sortBy('sequence')->values()->get(1);

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 150,
        'date' => '2026-02-01',
        'commitment_id' => $first->id,
        'allow_overpayment' => true,
    ]);

    expect($payment->transaction->amount)->toBe('150.00')
        ->and($first->fresh()->allocatedAmount())->toBe('100.00')
        ->and($first->fresh()->isSatisfied())->toBeTrue()
        ->and($second->fresh()->allocatedAmount())->toBe('0.00')
        ->and($payment->unallocatedAmount)->toBe('50.00');
});

it('supports partial allocation across multiple transactions for one commitment', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 1,
        'first_due_date' => '2026-02-01',
    ]);

    $commitment = $result->commitments->first();
    $service = app(LoanPaymentService::class);
    $allocator = app(CommitmentAllocationService::class);

    $firstPayment = $service->registerPayment($user, $result->loan, [
        'amount' => 60,
        'date' => '2026-02-01',
    ]);

    expect($firstPayment->allocations)->toHaveCount(0)
        ->and($commitment->fresh()->allocatedAmount())->toBe('0.00');

    $allocator->allocate($commitment, $firstPayment->transaction, 60);

    $secondPayment = $service->registerPayment($user, $result->loan->fresh(), [
        'amount' => 40,
        'date' => '2026-02-15',
    ]);

    $allocator->allocate($commitment->fresh(), $secondPayment->transaction, 40);

    expect($commitment->fresh()->isSatisfied())->toBeTrue()
        ->and($commitment->fresh()->allocatedAmount())->toBe('100.00')
        ->and(Transaction::query()->where('type', TransactionType::INCOME)->count())->toBe(2);
});

it('allows a transaction with zero commitment allocations', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 1,
        'first_due_date' => '2026-02-01',
    ]);

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 100,
        'date' => '2026-02-01',
    ]);

    expect($payment->transaction->commitments()->count())->toBe(0)
        ->and($result->loan->fresh()->status)->toBe(LoanStatus::CLOSED)
        ->and($result->commitments->first()->fresh()->isSatisfied())->toBeFalse();
});

it('keeps full transaction amount on overpayment with unallocated excess', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 1,
        'first_due_date' => '2026-02-01',
    ]);

    $commitment = $result->commitments->first();

    $payment = app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 150,
        'date' => '2026-02-01',
        'commitment_id' => $commitment->id,
        'allow_overpayment' => true,
    ]);

    expect($payment->transaction->amount)->toBe('150.00')
        ->and($commitment->fresh()->allocatedAmount())->toBe('100.00')
        ->and($payment->unallocatedAmount)->toBe('50.00')
        ->and(app(LoanBalanceService::class)->remainingBalance($result->loan->fresh()))->toBe('-50.00');
});

it('computes loan balance from actual transactions not commitments', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 600,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 6,
        'first_due_date' => '2026-02-01',
    ]);

    $balance = app(LoanBalanceService::class);

    expect($balance->remainingBalance($result->loan))->toBe('600.00');

    app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 100,
        'date' => '2026-02-01',
    ]);

    expect($balance->remainingBalance($result->loan->fresh()))->toBe('500.00')
        ->and(Commitment::query()->where('loan_id', $result->loan->id)->count())->toBe(6)
        ->and($result->commitments->every(fn (Commitment $c) => $c->fresh()->allocatedAmount() === '0.00'))->toBeTrue();
});

it('leaves loan history unchanged when a commitment becomes overdue without payment', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 1,
        'first_due_date' => '2026-02-01',
    ]);

    Carbon::setTestNow('2026-03-15');

    expect(Transaction::query()->where('type', TransactionType::INCOME)->count())->toBe(0)
        ->and(app(LoanBalanceService::class)->remainingBalance($result->loan))->toBe('100.00')
        ->and($result->commitments->first()->fresh()->isSatisfied())->toBeFalse();

    Carbon::setTestNow();
});

it('does not recalculate commitments automatically after payment', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 600,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 6,
        'first_due_date' => '2026-02-01',
    ]);

    $idsBefore = Commitment::query()->where('loan_id', $result->loan->id)->pluck('id')->sort()->values()->all();

    app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 250,
        'date' => '2026-02-01',
        'allow_overpayment' => true,
    ]);

    $idsAfter = Commitment::query()->where('loan_id', $result->loan->id)->pluck('id')->sort()->values()->all();

    expect($idsAfter)->toBe($idsBefore)
        ->and(Commitment::onlyTrashed()->where('loan_id', $result->loan->id)->count())->toBe(0);
});

it('allows explicit schedule recalculation without rewriting history', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 600,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 6,
        'first_due_date' => '2026-02-01',
    ]);

    $service = app(LoanPaymentService::class);
    $allocator = app(CommitmentAllocationService::class);

    $first = $service->registerPayment($user, $result->loan, ['amount' => 100, 'date' => '2026-02-01']);
    $second = $service->registerPayment($user, $result->loan->fresh(), ['amount' => 100, 'date' => '2026-03-01']);

    $commitments = $result->loan->commitments()->orderBy('sequence')->get();
    $allocator->allocate($commitments[0], $first->transaction, 100);
    $allocator->allocate($commitments[1], $second->transaction, 100);

    $extra = $service->registerPayment($user, $result->loan->fresh(), [
        'amount' => 50,
        'date' => '2026-03-15',
        'allow_overpayment' => true,
    ]);

    expect($extra->allocations)->toHaveCount(0);

    $satisfiedIds = Commitment::query()
        ->where('loan_id', $result->loan->id)
        ->get()
        ->filter(fn (Commitment $c) => $c->isSatisfied())
        ->pluck('id')
        ->all();

    expect($satisfiedIds)->toHaveCount(2);

    $newCommitments = app(LoanCommitmentRecalculationService::class)
        ->redistributeRemainingEvenly($result->loan->fresh(), 3, '2026-04-01');

    expect($newCommitments)->toHaveCount(3)
        ->and(InstallmentDistributor::formatAmount($newCommitments->sum(fn (Commitment $c) => (float) $c->amount)))
        ->toBe('350.00');

    foreach ($satisfiedIds as $id) {
        expect(Commitment::withTrashed()->find($id)->trashed())->toBeFalse()
            ->and(Commitment::find($id)->isSatisfied())->toBeTrue();
    }

    expect(Transaction::query()->where('type', TransactionType::INCOME)->count())->toBe(3)
        ->and(app(LoanBalanceService::class)->remainingBalance($result->loan->fresh()))->toBe('350.00');
});

it('distributes remaining balance with exact money rounding', function () {
    $amounts = InstallmentDistributor::distributeAmounts('350.00', 3);

    expect($amounts)->toBe(['116.66', '116.66', '116.68'])
        ->and(bcadd(bcadd($amounts[0], $amounts[1], 2), $amounts[2], 2))->toBe('350.00');
});

it('rejects allocations that exceed commitment remaining or transaction unallocated', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
        'installments_count' => 1,
        'first_due_date' => '2026-02-01',
    ]);

    $commitment = $result->commitments->first();

    $transaction = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => TransactionType::INCOME,
        'amount' => 40,
        'status' => 'PAID',
        'date' => '2026-02-01',
        'loan_id' => $result->loan->id,
        'person_id' => $person->id,
    ]);

    $allocator = app(CommitmentAllocationService::class);

    expect(fn () => $allocator->allocate($commitment, $transaction, 50))
        ->toThrow(CommitmentAllocationException::class);

    $allocator->allocate($commitment, $transaction, 40);

    $extra = Transaction::query()->create([
        'user_id' => $user->id,
        'type' => TransactionType::INCOME,
        'amount' => 100,
        'status' => 'PAID',
        'date' => '2026-02-02',
        'loan_id' => $result->loan->id,
        'person_id' => $person->id,
    ]);

    expect(fn () => $allocator->allocate($commitment->fresh(), $extra, 70))
        ->toThrow(CommitmentAllocationException::class, 'A alocação excede o valor restante do compromisso.');
});

it('blocks loan repayment overpayment by default', function () {
    $user = commitmentDomainUser();
    $person = commitmentDomainPerson($user);

    $result = app(LoanOriginationService::class)->create($user, [
        'type' => LoanType::LENT,
        'original_amount' => 100,
        'person_id' => $person->id,
        'date' => '2026-01-01',
    ]);

    expect(fn () => app(LoanPaymentService::class)->registerPayment($user, $result->loan, [
        'amount' => 150,
        'date' => '2026-02-01',
    ]))->toThrow(LoanPaymentException::class);
});

it('has no standalone Commitment Filament resource', function () {
    expect(class_exists('App\\Filament\\Resources\\Commitments\\CommitmentResource'))->toBeFalse()
        ->and(File::isDirectory(app_path('Filament/Resources/Commitments')))->toBeFalse();
});
