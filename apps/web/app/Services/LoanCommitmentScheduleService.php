<?php

namespace App\Services;

use App\Enums\CommitmentChannel;
use App\Enums\CommitmentType;
use App\Enums\LoanType;
use App\Models\Commitment;
use App\Models\Loan;
use App\Support\InstallmentDistributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanCommitmentScheduleService
{
    /**
     * Create expected repayment Commitments for a Loan.
     * These are NOT Transactions — money has not moved yet.
     *
     * @return Collection<int, Commitment>
     */
    public function generate(
        Loan $loan,
        int $installmentsCount,
        Carbon|string $firstDueDate,
        string|float|int|null $totalAmount = null,
    ): Collection {
        $total = InstallmentDistributor::formatAmount($totalAmount ?? $loan->original_amount);

        if ($installmentsCount < 1 || $installmentsCount > InstallmentDistributor::MAX_INSTALLMENTS) {
            throw new \InvalidArgumentException('Número de compromissos inválido.');
        }

        if ($installmentsCount === 1) {
            $amounts = [$total];
            $dates = [Carbon::parse($firstDueDate)->startOfDay()];
        } else {
            $amounts = InstallmentDistributor::distributeAmounts($total, $installmentsCount);
            $dates = InstallmentDistributor::generateDates($firstDueDate, $installmentsCount);
        }

        return DB::transaction(function () use ($loan, $amounts, $dates): Collection {
            $commitments = collect();

            foreach ($amounts as $index => $amount) {
                $sequence = $index + 1;
                $dueDate = $dates[$index];

                $commitments->push(Commitment::query()->create([
                    'user_id' => $loan->user_id,
                    'person_id' => $loan->person_id,
                    'loan_id' => $loan->id,
                    'title' => $this->defaultTitle($loan, $sequence),
                    'description' => $loan->description,
                    'amount' => $amount,
                    'sequence' => $sequence,
                    'type' => CommitmentType::LOAN,
                    'event_date' => $dueDate->toDateString(),
                    'notification_offsets' => [
                        ['value' => 0, 'unit' => 'DAY'],
                    ],
                    'channels' => [CommitmentChannel::EMAIL->value],
                    'next_run_at' => $dueDate->copy()->startOfDay(),
                    'recurrence' => null,
                    'is_active' => true,
                ]));
            }

            return $commitments;
        });
    }

    private function defaultTitle(Loan $loan, int $sequence): string
    {
        $person = $loan->person?->name;

        return match ($loan->type) {
            LoanType::LENT => filled($person)
                ? "Recebimento esperado de {$person} (#{$sequence})"
                : "Recebimento esperado (#{$sequence})",
            LoanType::BORROWED => filled($person)
                ? "Pagamento esperado a {$person} (#{$sequence})"
                : "Pagamento esperado (#{$sequence})",
            default => "Compromisso do empréstimo (#{$sequence})",
        };
    }
}
