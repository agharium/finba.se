@php
    use App\Support\DashboardMetrics;
@endphp

<div class="finba-dashboard-section finba-dashboard-tithe">
    <div class="finba-dashboard-section__header">
        <h2 class="finba-dashboard-section__title">Dízimos e primícias</h2>
    </div>

    <div class="finba-dashboard-tithe__card">
        <div class="finba-dashboard-tithe__rows">
            <div class="finba-dashboard-tithe__row">
                <span class="finba-dashboard-tithe__label">Saldo a dizimar</span>
                <span class="finba-dashboard-tithe__value">
                    {{ DashboardMetrics::formatMoney($summary['tithe_pending']) }}
                </span>
            </div>

            <div class="finba-dashboard-tithe__row">
                <span class="finba-dashboard-tithe__label">Oferta complementar</span>
                <span class="finba-dashboard-tithe__value">
                    {{ DashboardMetrics::formatMoney($summary['offering_pending']) }}
                </span>
            </div>

            <div class="finba-dashboard-tithe__row">
                <span class="finba-dashboard-tithe__label">Primícias</span>
                <span class="finba-dashboard-tithe__value">
                    {{ DashboardMetrics::formatMoney($summary['firstfruits_pending']) }}
                </span>
            </div>

            <div class="finba-dashboard-tithe__row finba-dashboard-tithe__row--total">
                <span class="finba-dashboard-tithe__label">Total pendente</span>
                <span class="finba-dashboard-tithe__value finba-dashboard-tithe__value--total">
                    {{ DashboardMetrics::formatMoney($summary['combined']) }}
                </span>
            </div>
        </div>

        @if ($ctaEnabled)
            {{ $this->deliverAction }}
        @else
            <button
                type="button"
                disabled
                class="finba-dashboard-tithe__cta finba-dashboard-tithe__cta--disabled"
            >
                {{ $ctaLabel }}
            </button>
        @endif
    </div>

    <x-filament-actions::modals />
</div>
