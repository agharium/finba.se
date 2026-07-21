<?php

namespace App\Services;

use App\Enums\InstallmentGroupStatus;
use App\Enums\TransactionEntryMode;
use App\Enums\TransactionType;
use App\Exceptions\InstallmentCreationException;
use App\Models\Category;
use App\Models\InstallmentGroup;
use App\Models\Person;
use App\Models\Transaction;
use App\Models\User;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Facades\DB;

class InstallmentGroupService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function supports(array $data): bool
    {
        $mode = $data['entry_mode'] ?? TransactionEntryMode::IMMEDIATE->value;

        if ($mode instanceof TransactionEntryMode) {
            $mode = $mode->value;
        }

        return $mode === TransactionEntryMode::INSTALLMENT->value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): InstallmentCreationResult
    {
        $data['user_id'] = $user->id;
        $data['status'] ??= 'PAID';

        $installmentsCount = (int) ($data['installments_count'] ?? 0);
        $firstDate = $data['date'] ?? $data['first_installment_date'] ?? null;
        $totalAmount = InstallmentDistributor::formatAmount($data['amount'] ?? 0);
        $type = $data['type'] ?? null;

        if ($type instanceof TransactionType) {
            $type = $type->value;
        }

        if (! is_string($type) || blank($type)) {
            throw InstallmentCreationException::typeRequired();
        }

        if (
            $installmentsCount < InstallmentDistributor::MIN_INSTALLMENTS
            || $installmentsCount > InstallmentDistributor::MAX_INSTALLMENTS
        ) {
            throw InstallmentCreationException::invalidCount();
        }

        if (bccomp($totalAmount, '0.00', 2) !== 1) {
            throw InstallmentCreationException::invalidAmount();
        }

        if (blank($firstDate)) {
            throw InstallmentCreationException::firstDateRequired();
        }

        $prepared = app(TransactionService::class)->prepareAttributes([
            ...$data,
            'type' => $type,
            'amount' => $totalAmount,
            'date' => $firstDate,
        ]);

        $this->assertOwnedRelations($user, $prepared);

        $amounts = InstallmentDistributor::distributeAmounts($totalAmount, $installmentsCount);
        $dates = InstallmentDistributor::generateDates($firstDate, $installmentsCount);

        return DB::transaction(function () use ($user, $prepared, $totalAmount, $installmentsCount, $firstDate, $amounts, $dates): InstallmentCreationResult {
            $group = InstallmentGroup::query()->create([
                'user_id' => $user->id,
                'category_id' => $prepared['category_id'] ?? null,
                'person_id' => $prepared['person_id'] ?? null,
                'total_amount' => $totalAmount,
                'installments_count' => $installmentsCount,
                'description' => $prepared['description'] ?? null,
                'first_date' => $firstDate,
                'status' => InstallmentGroupStatus::ACTIVE,
            ]);

            $transactions = collect();

            foreach ($amounts as $index => $amount) {
                $number = $index + 1;

                $transactions->push(Transaction::query()->create([
                    ...$prepared,
                    'amount' => $amount,
                    'date' => $dates[$index]->toDateString(),
                    'installment_group_id' => $group->id,
                    'installment_number' => $number,
                ]));
            }

            return new InstallmentCreationResult($group, $transactions);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertOwnedRelations(User $user, array $attributes): void
    {
        if (filled($attributes['category_id'] ?? null)) {
            $owned = Category::query()
                ->where('user_id', $user->id)
                ->whereKey($attributes['category_id'])
                ->exists();

            if (! $owned) {
                throw InstallmentCreationException::foreignOwnership();
            }
        }

        if (filled($attributes['person_id'] ?? null)) {
            $owned = Person::query()
                ->where('user_id', $user->id)
                ->whereKey($attributes['person_id'])
                ->exists();

            if (! $owned) {
                throw InstallmentCreationException::foreignOwnership();
            }
        }

        // geo_city_id is an external Geo integer — no local ownership check.
    }
}
