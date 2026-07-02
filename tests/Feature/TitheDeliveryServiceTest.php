<?php

use App\Enums\Purpose;
use App\Enums\TransactionType;
use App\Exceptions\TitheDeliveryException;
use App\Models\TitheCalculation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TitheDeliverySelection;
use App\Services\TitheDeliveryService;
use App\Support\TitheMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createTither(array $settings = []): User
{
    return User::query()->create([
        'name' => 'Test Tither',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'email_verified_at' => now(),
        'settings' => array_merge([
            'advanced' => false,
            'tither' => true,
            'accounts_receivable' => false,
        ], $settings),
    ]);
}

function createIncome(User $user, float $amount, Carbon $date, array $attributes = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => $user->id,
        'type' => TransactionType::INCOME->value,
        'amount' => $amount,
        'date' => $date,
        'status' => 'PAID',
    ], $attributes));
}

function createContributionExpense(User $user, float $amount, Carbon $date, Purpose $purpose): Transaction
{
    return Transaction::query()->create([
        'user_id' => $user->id,
        'type' => TransactionType::EXPENSE->value,
        'purpose' => $purpose->value,
        'amount' => $amount,
        'date' => $date,
        'status' => 'PAID',
    ]);
}

function deliverSelection(
    User $user,
    Carbon $month,
    bool $tithe = false,
    bool $offering = false,
    bool $firstfruits = false,
) {
    return app(TitheDeliveryService::class)->deliver(
        $user,
        $month,
        new TitheDeliverySelection($tithe, $offering, $firstfruits),
    );
}

it('delivers only tithe when selected', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 14));

    $result = deliverSelection($user, $month, tithe: true);

    expect($result->deliveryTransactions)->toHaveCount(1)
        ->and($result->deliveryTransactions->first()->purpose)->toBe(Purpose::TITHE)
        ->and($result->calculation->tithe_amount)->toBe('100.00')
        ->and($result->calculation->offering_paid_amount)->toBe('0.00')
        ->and($result->calculation->firstfruits_amount)->toBe('0.00');

    $summary = (new TitheMetrics((string) $user->id, $month))->summary();

    expect($summary['tithe_pending'])->toBe(0)
        ->and($summary['offering_pending'])->toBe(100.0)
        ->and($summary['firstfruits_pending'])->toBe(33.33);
});

it('delivers tithe and firstfruits when selected', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 14));

    $result = deliverSelection($user, $month, tithe: true, firstfruits: true);

    expect($result->deliveryTransactions)->toHaveCount(2);

    $purposes = $result->deliveryTransactions->pluck('purpose')->map->value->all();

    expect($purposes)->toContain(Purpose::TITHE->value, Purpose::FIRSTFRUITS->value);
});

it('delivers tithe offering and firstfruits when selected', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 14));

    $result = deliverSelection($user, $month, tithe: true, offering: true, firstfruits: true);

    expect($result->deliveryTransactions)->toHaveCount(3);

    $purposes = $result->deliveryTransactions->pluck('purpose')->map->value->all();

    expect($purposes)->toContain(
        Purpose::TITHE->value,
        Purpose::OFFERING->value,
        Purpose::FIRSTFRUITS->value,
    );
});

it('reduces offering pending by offering already delivered', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 10));
    createContributionExpense($user, 40, Carbon::create(2026, 6, 12), Purpose::OFFERING);

    $summary = (new TitheMetrics((string) $user->id, $month))->summary();

    expect($summary['offering_target'])->toBe(100.0)
        ->and($summary['offering_paid'])->toBe(40.0)
        ->and($summary['offering_pending'])->toBe(60.0);
});

it('allows partial deliveries without blocking remaining items', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 10));

    deliverSelection($user, $month, tithe: true);
    $second = deliverSelection($user, $month, firstfruits: true);

    expect(TitheCalculation::query()->count())->toBe(2)
        ->and($second->deliveryTransactions)->toHaveCount(1)
        ->and($second->deliveryTransactions->first()->purpose)->toBe(Purpose::FIRSTFRUITS);
});

it('prevents delivering an item without pending amount', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 10));
    deliverSelection($user, $month, tithe: true);

    deliverSelection($user, $month, tithe: true);
})->throws(TitheDeliveryException::class, 'dízimo');

it('throws when nothing is selected', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 10));

    deliverSelection($user, $month);
})->throws(TitheDeliveryException::class, 'Selecione ao menos um item');

it('updates dashboard pending values after delivery', function () {
    $user = createTither();
    $month = Carbon::create(2026, 3, 1);

    createIncome($user, 3000, Carbon::create(2026, 3, 5));

    $before = (new TitheMetrics((string) $user->id, $month))->summary();

    expect($before['combined'])->toBeGreaterThan(0);

    deliverSelection($user, $month, tithe: true, offering: true, firstfruits: true);

    $after = (new TitheMetrics((string) $user->id, $month))->summary();

    expect($after['tithe_pending'])->toBe(0)
        ->and($after['offering_pending'])->toBe(0)
        ->and($after['firstfruits_pending'])->toBe(0)
        ->and($after['combined'])->toEqual(0);
});

it('excludes contribution incomes from eligible base amount', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    createIncome($user, 1000, Carbon::create(2026, 6, 10));
    createIncome($user, 500, Carbon::create(2026, 6, 12), [
        'purpose' => Purpose::TITHE->value,
    ]);

    $summary = (new TitheMetrics((string) $user->id, $month))->summary();

    expect($summary['base_amount'])->toBe(1000.0)
        ->and($summary['tithe_pending'])->toBe(0)
        ->and($summary['firstfruits_pending'])->toBeGreaterThan(0);
});

it('throws when there is nothing pending for delivery', function () {
    $user = createTither();
    $month = Carbon::create(2026, 6, 1);

    deliverSelection($user, $month, tithe: true);
})->throws(TitheDeliveryException::class, 'Não há valores pendentes');

it('delivers separately for different months', function () {
    $user = createTither();

    createIncome($user, 1000, Carbon::create(2026, 5, 10));
    createIncome($user, 800, Carbon::create(2026, 6, 10));

    deliverSelection($user, Carbon::create(2026, 5, 1), tithe: true, offering: true, firstfruits: true);

    $juneSummary = (new TitheMetrics((string) $user->id, Carbon::create(2026, 6, 1)))->summary();

    expect($juneSummary['combined'])->toBeGreaterThan(0)
        ->and(TitheCalculation::query()->where('user_id', $user->id)->count())->toBe(1);
});
