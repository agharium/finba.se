<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Enums\TransactionType;
use App\Exceptions\TitheDeliveryException;
use App\Models\TitheCalculation;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Helpers;
use App\Support\TitheMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TitheDeliveryService
{
    public function deliver(User $user, Carbon $month, TitheDeliverySelection $selection): TitheDeliveryResult
    {
        if (! $user->isTither()) {
            throw TitheDeliveryException::notTither();
        }

        if (! $selection->hasSelection()) {
            throw TitheDeliveryException::nothingSelected();
        }

        $periodStart = $month->copy()->startOfMonth()->startOfDay();
        $periodEnd = $month->copy()->endOfMonth()->startOfDay();
        $monthLabel = Helpers::monthLabelPtBr($month->month) . ' ' . $month->year;

        $metrics = new TitheMetrics((string) $user->id, $periodStart);
        $summary = $metrics->summary();

        if (! TitheMetrics::ctaEnabled($summary)) {
            throw TitheDeliveryException::nothingPending();
        }

        $this->validateSelection($selection, $summary);

        return DB::transaction(function () use ($user, $periodStart, $periodEnd, $monthLabel, $metrics, $summary, $selection): TitheDeliveryResult {
            $deliveryDate = $periodStart->isSameMonth(now()) ? now()->startOfDay() : $periodEnd;

            $titheAmount = $selection->deliverTithe ? $summary['tithe_pending'] : 0;
            $offeringAmount = $selection->deliverOffering ? $summary['offering_pending'] : 0;
            $firstfruitsAmount = $selection->deliverFirstfruits ? $summary['firstfruits_pending'] : 0;

            $calculation = TitheCalculation::query()->create([
                'user_id' => $user->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'base_amount' => $summary['base_amount'],
                'tithe_amount' => $titheAmount,
                'offering_target_amount' => $summary['offering_target'],
                'offering_paid_amount' => $offeringAmount,
                'firstfruits_amount' => $firstfruitsAmount,
            ]);

            $unlinkableIncomes = $metrics->unlinkableEligibleIncomeTransactions();

            if ($unlinkableIncomes->isNotEmpty()) {
                $calculation->incomeTransactions()->attach($unlinkableIncomes->pluck('id'));
            }

            $deliveryTransactions = collect();

            if ($titheAmount > 0) {
                $deliveryTransactions->push(
                    $this->createDeliveryTransaction(
                        user: $user,
                        calculation: $calculation,
                        purpose: Purpose::TITHE,
                        amount: $titheAmount,
                        description: sprintf('Entrega de dízimos — %s', $monthLabel),
                        date: $deliveryDate,
                    ),
                );
            }

            if ($offeringAmount > 0) {
                $deliveryTransactions->push(
                    $this->createDeliveryTransaction(
                        user: $user,
                        calculation: $calculation,
                        purpose: Purpose::OFFERING,
                        amount: $offeringAmount,
                        description: sprintf('Entrega de oferta complementar — %s', $monthLabel),
                        date: $deliveryDate,
                    ),
                );
            }

            if ($firstfruitsAmount > 0) {
                $deliveryTransactions->push(
                    $this->createDeliveryTransaction(
                        user: $user,
                        calculation: $calculation,
                        purpose: Purpose::FIRSTFRUITS,
                        amount: $firstfruitsAmount,
                        description: sprintf('Entrega de primícias — %s', $monthLabel),
                        date: $deliveryDate,
                    ),
                );
            }

            return new TitheDeliveryResult($calculation, $deliveryTransactions);
        });
    }

    /**
     * @param  array{
     *     tithe_pending: float,
     *     offering_pending: float,
     *     firstfruits_pending: float,
     * }  $summary
     */
    private function validateSelection(TitheDeliverySelection $selection, array $summary): void
    {
        if ($selection->deliverTithe && $summary['tithe_pending'] <= 0) {
            throw TitheDeliveryException::itemNotPending('dízimo');
        }

        if ($selection->deliverOffering && $summary['offering_pending'] <= 0) {
            throw TitheDeliveryException::itemNotPending('oferta complementar');
        }

        if ($selection->deliverFirstfruits && $summary['firstfruits_pending'] <= 0) {
            throw TitheDeliveryException::itemNotPending('primícias');
        }
    }

    private function createDeliveryTransaction(
        User $user,
        TitheCalculation $calculation,
        Purpose $purpose,
        float $amount,
        string $description,
        Carbon $date,
    ): Transaction {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'type' => TransactionType::EXPENSE->value,
            'purpose' => $purpose->value,
            'amount' => $amount,
            'description' => $description,
            'date' => $date,
            'status' => 'PAID',
            'tithe_calculation_id' => $calculation->id,
        ]);
    }
}
