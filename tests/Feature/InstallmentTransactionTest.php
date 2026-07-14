<?php

use App\Enums\IncomePaymentMode;
use App\Enums\InstallmentGroupStatus;
use App\Enums\TransactionEntryMode;
use App\Enums\TransactionType;
use App\Exceptions\InstallmentCreationException;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Category;
use App\Models\City;
use App\Models\InstallmentGroup;
use App\Models\Loan;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use App\Support\InstallmentDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function installmentUser(array $settings = []): User
{
    return User::query()->create([
        'name' => 'Installment User',
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

function installmentCategory(User $user, string $type = 'EXPENSE'): Category
{
    return Category::query()->create([
        'name' => 'Categoria '.$type,
        'types' => [$type],
        'user_id' => $user->id,
    ]);
}

function installmentPerson(User $user): Person
{
    return Person::query()->create([
        'name' => 'Pessoa Instalment',
        'types' => ['CONTACT'],
        'user_id' => $user->id,
    ]);
}

function installmentCity(User $user): City
{
    return City::query()->create([
        'name' => 'Tramandaí',
        'region_code' => 'RS',
        'country_code' => 'BR',
        'user_id' => $user->id,
    ]);
}

it('creates twelve installment transactions linked to one group', function () {
    $user = installmentUser();
    $category = installmentCategory($user);
    $person = installmentPerson($user);
    $city = installmentCity($user);

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '1200.00',
        'installments_count' => 12,
        'date' => '2026-08-05',
        'description' => 'Compra parcelada',
        'category_id' => $category->id,
        'person_id' => $person->id,
        'city_id' => $city->id,
        'status' => 'PAID',
    ]);

    expect($result->isReceivableSale)->toBeFalse()
        ->and($result->record)->toBeInstanceOf(Transaction::class)
        ->and(InstallmentGroup::query()->count())->toBe(1)
        ->and(Transaction::query()->count())->toBe(12);

    $group = InstallmentGroup::query()->first();

    expect($group->user_id)->toBe($user->id)
        ->and($group->total_amount)->toBe('1200.00')
        ->and($group->installments_count)->toBe(12)
        ->and($group->first_date->toDateString())->toBe('2026-08-05')
        ->and($group->status)->toBe(InstallmentGroupStatus::ACTIVE)
        ->and($group->category_id)->toBe($category->id)
        ->and($group->person_id)->toBe($person->id);

    $transactions = Transaction::query()->orderBy('installment_number')->get();

    expect($transactions->pluck('installment_group_id')->unique()->all())->toBe([$group->id])
        ->and($transactions->pluck('installment_number')->all())->toBe(range(1, 12))
        ->and($transactions->every(fn (Transaction $tx): bool => $tx->city_id === $city->id))->toBeTrue()
        ->and($transactions->sum(fn (Transaction $tx): float => (float) $tx->amount))->toEqual(1200.0);
});

it('distributes residual cents on the final installment', function () {
    expect(InstallmentDistributor::distributeAmounts('1000.00', 3))->toBe([
        '333.33',
        '333.33',
        '333.34',
    ]);

    $user = installmentUser();

    app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '1000.00',
        'installments_count' => 3,
        'date' => '2026-01-10',
        'description' => 'Centavos',
        'status' => 'PAID',
    ]);

    $amounts = Transaction::query()->orderBy('installment_number')->pluck('amount')->all();

    expect($amounts)->toBe(['333.33', '333.33', '333.34'])
        ->and(InstallmentDistributor::formatAmount(array_sum(array_map('floatval', $amounts))))->toBe('1000.00');
});

it('generates monthly dates without month-end drift', function () {
    $dates = InstallmentDistributor::generateDates('2026-01-31', 4);

    expect($dates[0]->toDateString())->toBe('2026-01-31')
        ->and($dates[1]->toDateString())->toBe('2026-02-28')
        ->and($dates[2]->toDateString())->toBe('2026-03-31')
        ->and($dates[3]->toDateString())->toBe('2026-04-30');

    $user = installmentUser();

    app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '400.00',
        'installments_count' => 4,
        'date' => '2026-01-31',
        'description' => 'Fim de mês',
        'status' => 'PAID',
    ]);

    expect(Transaction::query()->orderBy('installment_number')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']);
});

it('keeps the normal immediate flow creating a single transaction', function () {
    $user = installmentUser();

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::IMMEDIATE->value,
        'amount' => '50.00',
        'description' => 'À vista',
        'date' => now()->toDateString(),
        'status' => 'PAID',
    ]);

    expect($result->record)->toBeInstanceOf(Transaction::class)
        ->and(Transaction::query()->count())->toBe(1)
        ->and(InstallmentGroup::query()->count())->toBe(0)
        ->and($result->record->installment_group_id)->toBeNull();
});

it('does not create installments for receivable sales', function () {
    $user = installmentUser();
    $person = installmentPerson($user);

    $result = app(TransactionService::class)->create($user, [
        'type' => TransactionType::INCOME->value,
        'payment_mode' => IncomePaymentMode::LATER->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '900.00',
        'installments_count' => 6,
        'date' => now()->toDateString(),
        'description' => 'Venda a prazo',
        'person_id' => $person->id,
        'status' => 'PAID',
    ]);

    expect($result->isReceivableSale)->toBeTrue()
        ->and($result->record)->toBeInstanceOf(Loan::class)
        ->and(InstallmentGroup::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('rejects another users category person or city', function () {
    $user = installmentUser();
    $other = installmentUser();
    $foreignCategory = installmentCategory($other);
    $foreignPerson = installmentPerson($other);
    $foreignCity = installmentCity($other);

    expect(fn () => app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '200.00',
        'installments_count' => 2,
        'date' => '2026-03-01',
        'category_id' => $foreignCategory->id,
        'status' => 'PAID',
    ]))->toThrow(InstallmentCreationException::class);

    expect(fn () => app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '200.00',
        'installments_count' => 2,
        'date' => '2026-03-01',
        'person_id' => $foreignPerson->id,
        'status' => 'PAID',
    ]))->toThrow(InstallmentCreationException::class);

    expect(fn () => app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '200.00',
        'installments_count' => 2,
        'date' => '2026-03-01',
        'city_id' => $foreignCity->id,
        'status' => 'PAID',
    ]))->toThrow(InstallmentCreationException::class);

    expect(InstallmentGroup::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('displays parcela x/y for grouped transactions', function () {
    $user = installmentUser();

    app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '300.00',
        'installments_count' => 3,
        'date' => '2026-05-01',
        'description' => 'Notebook',
        'status' => 'PAID',
    ]);

    $third = Transaction::query()->where('installment_number', 3)->first();
    $third->load('installmentGroup');

    expect(TransactionResource::installmentLabel($third))->toBe('Parcela 3/3')
        ->and(TransactionResource::mobileCardData($third)['installment'])->toBe('Parcela 3/3');
});

it('rolls back the group when installment generation fails', function () {
    $user = installmentUser();

    Transaction::creating(function (): void {
        static $count = 0;

        if (++$count === 2) {
            throw new RuntimeException('Forced failure');
        }
    });

    expect(fn () => app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '600.00',
        'installments_count' => 3,
        'date' => '2026-06-01',
        'description' => 'Falha',
        'status' => 'PAID',
    ]))->toThrow(RuntimeException::class);

    expect(InstallmentGroup::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);
});

it('blocks deletion of grouped installment transactions', function () {
    $user = installmentUser();

    app(TransactionService::class)->create($user, [
        'type' => TransactionType::EXPENSE->value,
        'entry_mode' => TransactionEntryMode::INSTALLMENT->value,
        'amount' => '100.00',
        'installments_count' => 2,
        'date' => '2026-07-01',
        'description' => 'Bloqueio',
        'status' => 'PAID',
    ]);

    $record = Transaction::query()->first();

    expect(fn () => TransactionResource::guardGroupedInstallmentDeletion($record))
        ->toThrow(\Filament\Support\Exceptions\Halt::class);
});
