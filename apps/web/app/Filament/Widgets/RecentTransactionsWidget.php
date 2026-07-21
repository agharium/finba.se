<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\Transaction;
use App\Support\DashboardMetrics;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\ViewAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class RecentTransactionsWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithDashboardPeriod;
    use InteractsWithSchemas;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.recent-transactions-widget';

    public function viewTransactionAction(): ViewAction
    {
        return TransactionResource::makeViewAction('viewTransaction')
            ->label('Visualizar')
            ->record(fn (array $arguments): ?string => $arguments['transaction'] ?? null)
            ->resolveRecordUsing(fn (string $key): Transaction => TransactionResource::getEloquentQuery()
                ->with(['category.parent', 'person', 'city'])
                ->findOrFail($key));
    }

    protected function getMountedActionSchemaModel(): Model|string|null
    {
        return Transaction::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->dashboardPeriod();

        return [
            'transactions' => $this->dashboardMetrics()->recentTransactions(),
            'transactionsUrl' => DashboardMetrics::transactionsUrl('incomes', $period->year, $period->month),
        ];
    }
}
