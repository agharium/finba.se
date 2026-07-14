<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

class InstallmentDistributor
{
    public const MIN_INSTALLMENTS = 2;

    public const MAX_INSTALLMENTS = 120;

    /**
     * Split total into N amounts with exactly 2 decimal places.
     * Residual cents are placed on the final installment so the sum matches the total.
     *
     * @return list<string>
     */
    public static function distributeAmounts(string|float|int $totalAmount, int $installmentsCount): array
    {
        if ($installmentsCount < self::MIN_INSTALLMENTS || $installmentsCount > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('Número de parcelas inválido.');
        }

        $total = self::formatAmount($totalAmount);

        if (bccomp($total, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('O valor total deve ser maior que zero.');
        }

        $totalCents = (int) bcmul($total, '100', 0);
        $baseCents = intdiv($totalCents, $installmentsCount);
        $remainderCents = $totalCents % $installmentsCount;

        $amounts = [];

        for ($i = 1; $i <= $installmentsCount; $i++) {
            $cents = $baseCents;

            if ($i === $installmentsCount) {
                $cents += $remainderCents;
            }

            $amounts[] = self::formatAmount(bcdiv((string) $cents, '100', 2));
        }

        return $amounts;
    }

    /**
     * Generate monthly dates from the first installment date.
     *
     * Always advances from the original first date with Carbon::addMonthsNoOverflow,
     * so month-end days clamp without drifting (31 Jan → 28/29 Feb → 31 Mar → 30 Apr).
     *
     * @return list<Carbon>
     */
    public static function generateDates(Carbon|string $firstDate, int $installmentsCount): array
    {
        if ($installmentsCount < self::MIN_INSTALLMENTS || $installmentsCount > self::MAX_INSTALLMENTS) {
            throw new InvalidArgumentException('Número de parcelas inválido.');
        }

        $start = Carbon::parse($firstDate)->startOfDay();
        $dates = [];

        for ($i = 0; $i < $installmentsCount; $i++) {
            $dates[] = $start->copy()->addMonthsNoOverflow($i);
        }

        return $dates;
    }

    public static function formatAmount(string|float|int|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
