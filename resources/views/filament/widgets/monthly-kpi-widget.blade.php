@php
    use App\Support\DashboardMetrics;
@endphp

<div class="finba-dashboard-section">
    <div class="finba-dashboard-kpis">
        <article @class([
            'finba-dashboard-kpi',
            'finba-dashboard-kpi--balance',
            'finba-dashboard-kpi--positive' => $balance > 0,
            'finba-dashboard-kpi--negative' => $balance < 0,
        ])>
            <div class="finba-dashboard-kpi__header">
                <span class="finba-dashboard-kpi__label">Saldo do mês</span>
                <x-filament::icon
                    icon="heroicon-m-scale"
                    class="finba-dashboard-kpi__icon"
                />
            </div>
            <p class="finba-dashboard-kpi__amount">{{ DashboardMetrics::formatBrl($balance) }}</p>
        </article>

        <a href="{{ $incomeUrl }}" class="finba-dashboard-kpi finba-dashboard-kpi--income finba-dashboard-kpi--link" aria-label="Ver receitas do mês">
            <div class="finba-dashboard-kpi__header">
                <span class="finba-dashboard-kpi__label">Receitas do mês</span>
                <x-filament::icon
                    icon="heroicon-m-arrow-trending-up"
                    class="finba-dashboard-kpi__icon finba-dashboard-kpi__icon--income"
                />
            </div>
            <p class="finba-dashboard-kpi__amount">{{ DashboardMetrics::formatBrl($income) }}</p>
        </a>

        <a href="{{ $expenseUrl }}" class="finba-dashboard-kpi finba-dashboard-kpi--expense finba-dashboard-kpi--link" aria-label="Ver despesas do mês">
            <div class="finba-dashboard-kpi__header">
                <span class="finba-dashboard-kpi__label">Despesas do mês</span>
                <x-filament::icon
                    icon="heroicon-m-arrow-trending-down"
                    class="finba-dashboard-kpi__icon finba-dashboard-kpi__icon--expense"
                />
            </div>
            <p class="finba-dashboard-kpi__amount">{{ DashboardMetrics::formatBrl($expense) }}</p>
        </a>
    </div>
</div>
