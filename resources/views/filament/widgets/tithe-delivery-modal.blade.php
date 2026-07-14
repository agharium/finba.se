@php
    use App\Support\DashboardMetrics;
    use App\Support\TitheMetrics;
    use App\Services\TitheDeliverySelection;

    $selection = new TitheDeliverySelection(
        deliverTithe: $deliverTithe,
        deliverOffering: $deliverOffering,
        deliverFirstfruits: $deliverFirstfruits,
    );

    $selectedTotal = TitheMetrics::selectedTotal($summary, $selection);
@endphp

<div class="finba-tithe-modal__content">
    <p class="finba-tithe-modal__intro">
        Escolha o que deseja entregar referente a <strong>{{ $monthLabel }}</strong>.
    </p>

    <div class="finba-tithe-modal__rows">
        <div @class([
            'finba-tithe-modal__row',
            'finba-tithe-modal__row--selectable' => $summary['tithe_pending'] > 0,
            'finba-tithe-modal__row--fulfilled' => $summary['tithe_pending'] <= 0,
        ])>
            <div class="finba-tithe-modal__row-main">
                <span class="finba-tithe-modal__label">Dízimo pendente</span>
                <span class="finba-tithe-modal__value">
                    @if ($summary['tithe_pending'] > 0)
                        {{ DashboardMetrics::formatMoney($summary['tithe_pending']) }}
                    @else
                        Já cumprido
                    @endif
                </span>
            </div>

            @if ($summary['tithe_pending'] > 0)
                <label class="finba-tithe-modal__toggle">
                    <input
                        type="checkbox"
                        wire:model.live="deliverTithe"
                        class="finba-tithe-modal__toggle-input"
                    >
                    <span>Entregar dízimo</span>
                </label>
            @endif
        </div>

        <div @class([
            'finba-tithe-modal__row',
            'finba-tithe-modal__row--selectable' => $summary['offering_pending'] > 0,
            'finba-tithe-modal__row--fulfilled' => $summary['offering_pending'] <= 0,
        ])>
            <div class="finba-tithe-modal__row-main">
                <span class="finba-tithe-modal__label">Oferta complementar pendente</span>
                <span class="finba-tithe-modal__value">
                    @if ($summary['offering_pending'] > 0)
                        {{ DashboardMetrics::formatMoney($summary['offering_pending']) }}
                    @else
                        Já cumprida
                    @endif
                </span>
            </div>

            @if ($summary['offering_pending'] > 0)
                <label class="finba-tithe-modal__toggle">
                    <input
                        type="checkbox"
                        wire:model.live="deliverOffering"
                        class="finba-tithe-modal__toggle-input"
                    >
                    <span>Entregar oferta complementar</span>
                </label>
            @endif
        </div>

        <div @class([
            'finba-tithe-modal__row',
            'finba-tithe-modal__row--selectable' => $summary['firstfruits_pending'] > 0,
            'finba-tithe-modal__row--fulfilled' => $summary['firstfruits_pending'] <= 0,
        ])>
            <div class="finba-tithe-modal__row-main">
                <span class="finba-tithe-modal__label">Primícias pendentes</span>
                <span class="finba-tithe-modal__value">
                    @if ($summary['firstfruits_pending'] > 0)
                        {{ DashboardMetrics::formatMoney($summary['firstfruits_pending']) }}
                    @else
                        Já cumpridas
                    @endif
                </span>
            </div>

            @if ($summary['firstfruits_pending'] > 0)
                <label class="finba-tithe-modal__toggle">
                    <input
                        type="checkbox"
                        wire:model.live="deliverFirstfruits"
                        class="finba-tithe-modal__toggle-input"
                    >
                    <span>Entregar primícias</span>
                </label>
            @endif
        </div>

        <div class="finba-tithe-modal__row finba-tithe-modal__row--total">
            <span class="finba-tithe-modal__label">Total selecionado</span>
            <span class="finba-tithe-modal__value finba-tithe-modal__value--total">
                {{ DashboardMetrics::formatMoney($selectedTotal) }}
            </span>
        </div>
    </div>

    <div class="finba-tithe-modal__pix-slot" data-finba-pix-integration></div>
</div>
