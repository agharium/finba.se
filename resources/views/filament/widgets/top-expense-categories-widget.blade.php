@php
    use App\Support\DashboardMetrics;
@endphp

<div class="finba-dashboard-section">
    <div class="finba-dashboard-section__header">
        <h2 class="finba-dashboard-section__title">Despesas por categoria</h2>
    </div>

    @if ($categories->isEmpty())
        <div class="finba-dashboard-empty">
            <p>Nenhuma despesa registrada neste período.</p>
        </div>
    @else
        <div class="finba-dashboard-categories">
            @foreach ($categories as $category)
                <article class="finba-dashboard-category">
                    <span class="finba-dashboard-category__name">{{ $category->category_name }}</span>
                    <span class="finba-dashboard-category__amount">
                        {{ DashboardMetrics::formatBrl($category->total) }}
                    </span>
                </article>
            @endforeach
        </div>
    @endif
</div>
