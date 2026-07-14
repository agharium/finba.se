@php
    use App\Support\DashboardMetrics;
@endphp

<div class="finba-dashboard-section">
    <div class="finba-dashboard-section__header">
        <h2 class="finba-dashboard-section__title">Últimas transações</h2>

        <a href="{{ $transactionsUrl }}" class="finba-dashboard-section__link">
            Ver todas
        </a>
    </div>

    @if ($transactions->isEmpty())
        <div class="finba-dashboard-empty">
            <p>Nenhuma transação registrada neste período.</p>
        </div>
    @else
        <div class="finba-dashboard-transactions">
            @foreach ($transactions as $transaction)
                @php
                    $categoryName = DashboardMetrics::categoryDisplayName($transaction);
                    $installmentLabel = \App\Filament\Resources\Transactions\TransactionResource::installmentLabel($transaction);
                @endphp

                <article
                    class="finba-dashboard-transaction finba-dashboard-transaction--clickable"
                    role="button"
                    tabindex="0"
                    wire:click="mountAction('viewTransaction', { transaction: '{{ $transaction->id }}' })"
                    wire:keydown.enter="mountAction('viewTransaction', { transaction: '{{ $transaction->id }}' })"
                    wire:key="dashboard-transaction-{{ $transaction->id }}"
                >
                    <div class="finba-dashboard-transaction__main">
                        @if (filled($transaction->description))
                            <p class="finba-dashboard-transaction__title">
                                {{ $transaction->description }}
                            </p>
                        @else
                            <p class="finba-dashboard-transaction__title finba-dashboard-transaction__title--muted">
                                Sem descrição
                            </p>
                        @endif

                        @if (filled($installmentLabel))
                            <span class="finba-dashboard-transaction__category">{{ $installmentLabel }}</span>
                        @elseif (filled($categoryName))
                            <span class="finba-dashboard-transaction__category">{{ $categoryName }}</span>
                        @endif
                    </div>

                    <div class="finba-dashboard-transaction__aside">
                        <p class="finba-dashboard-transaction__amount">
                            {{ DashboardMetrics::formatMoney($transaction->amount) }}
                        </p>
                        <p class="finba-dashboard-transaction__date">
                            {{ $transaction->date?->format('d/m/Y') }}
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</div>
