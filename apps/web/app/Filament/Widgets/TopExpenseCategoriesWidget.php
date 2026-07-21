<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use Filament\Widgets\Widget;

class TopExpenseCategoriesWidget extends Widget
{
    use InteractsWithDashboardPeriod;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.top-expense-categories-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'categories' => $this->dashboardMetrics()->topExpenseCategories(),
        ];
    }
}
