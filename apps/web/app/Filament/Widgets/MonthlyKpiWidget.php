<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Support\DashboardMetrics;
use Filament\Widgets\Widget;

class MonthlyKpiWidget extends Widget
{
    use InteractsWithDashboardPeriod;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.monthly-kpi-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $metrics = $this->dashboardMetrics();
        $totals = $metrics->monthlyTotals();
        $period = $this->dashboardPeriod();

        return [
            'income' => $totals['income'],
            'expense' => $totals['expense'],
            'balance' => $totals['balance'],
            'incomeUrl' => DashboardMetrics::transactionsUrl('incomes', $period->year, $period->month),
            'expenseUrl' => DashboardMetrics::transactionsUrl('expenses', $period->year, $period->month),
        ];
    }
}
