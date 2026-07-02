<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MonthlyKpiWidget;
use App\Filament\Widgets\RecentTransactionsWidget;
use App\Filament\Widgets\TitheSummaryWidget;
use App\Filament\Widgets\TopExpenseCategoriesWidget;
use App\Support\DashboardMetrics;
use App\Support\Helpers;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Início';

    protected static ?string $navigationLabel = 'Início';

    protected static ?int $navigationSort = -2;

    public function mount(): void
    {
        if (blank($this->filters['year'] ?? null) || blank($this->filters['month'] ?? null)) {
            $this->filters = [
                'year' => now()->year,
                'month' => now()->month,
            ];
        }
    }

    public function getSubheading(): string | Htmlable | null
    {
        $year = (int) ($this->filters['year'] ?? now()->year);
        $month = (int) ($this->filters['month'] ?? now()->month);

        return Helpers::monthLabelPtBr($month) . ' ' . $year;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->components([
                Select::make('year')
                    ->label('Ano')
                    ->options(fn (): array => DashboardMetrics::availableYearOptions())
                    ->default(now()->year)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),

                Select::make('month')
                    ->label('Mês')
                    ->options(fn (): array => DashboardMetrics::availableMonthOptions(
                        $this->filters['year'] ?? now()->year,
                    ))
                    ->default(now()->month)
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live(),
            ]);
    }

    public function getFiltersForm(): Schema
    {
        if ((! $this->isCachingSchemas) && $this->hasCachedSchema('filtersForm')) {
            return $this->getSchema('filtersForm');
        }

        $schema = $this->makeSchema()
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->extraAttributes([
                'wire:partial' => 'table-filters-form',
                'class' => 'finba-dashboard-filters',
            ])
            ->live()
            ->statePath('filters');

        return $this->filtersForm($schema);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            MonthlyKpiWidget::class,
            TitheSummaryWidget::class,
            RecentTransactionsWidget::class,
            TopExpenseCategoriesWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 1;
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'finba-dashboard-page',
        ];
    }
}
