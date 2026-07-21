<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\DashboardMetrics;
use App\Support\TitheMetrics;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

trait InteractsWithDashboardPeriod
{
    /**
     * @var array<string, mixed>|null
     */
    #[Reactive]
    public ?array $pageFilters = null;

    protected function dashboardPeriod(): Carbon
    {
        $year = (int) ($this->pageFilters['year'] ?? now()->year);
        $month = (int) ($this->pageFilters['month'] ?? now()->month);

        return Carbon::create($year, $month, 1)->startOfMonth();
    }

    protected function dashboardMetrics(): DashboardMetrics
    {
        return DashboardMetrics::forCurrentUser($this->dashboardPeriod());
    }

    protected function titheMetrics(): TitheMetrics
    {
        return TitheMetrics::forCurrentUser($this->dashboardPeriod());
    }
}
