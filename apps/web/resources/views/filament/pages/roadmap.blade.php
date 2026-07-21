<x-filament-panels::page>
    @php
        $counts = $this->getStatusCounts();
    @endphp

    <div class="finba-roadmap mx-auto w-full max-w-3xl">
        <section class="finba-roadmap-summary" aria-label="Resumo do roadmap">
            <article class="finba-roadmap-summary__card finba-roadmap-summary__card--completed">
                <span class="finba-roadmap-summary__value">{{ $counts['completed'] ?? 0 }}</span>
                <span class="finba-roadmap-summary__label">Concluídos</span>
            </article>

            <article class="finba-roadmap-summary__card finba-roadmap-summary__card--in-progress">
                <span class="finba-roadmap-summary__value">{{ $counts['in_progress'] ?? 0 }}</span>
                <span class="finba-roadmap-summary__label">Em desenvolvimento</span>
            </article>

            <article class="finba-roadmap-summary__card finba-roadmap-summary__card--planned">
                <span class="finba-roadmap-summary__value">{{ $counts['planned'] ?? 0 }}</span>
                <span class="finba-roadmap-summary__label">Planejados</span>
            </article>
        </section>

        @forelse ($this->getCategories() as $category)
            <section class="finba-roadmap-category" wire:key="roadmap-category-{{ md5($category['title']) }}">
                <header class="finba-roadmap-category__header">
                    <h2 class="finba-roadmap-category__title">
                        {{ $category['title'] }}
                    </h2>
                </header>

                <ul class="finba-roadmap-items">
                    @foreach ($category['items'] as $item)
                        <li
                            @class([
                                'finba-roadmap-item',
                                'finba-roadmap-item--' . $item['status'],
                            ])
                            wire:key="roadmap-item-{{ md5($category['title'] . $item['title']) }}"
                        >
                            <span class="finba-roadmap-item__icon" aria-hidden="true">
                                @if ($item['status'] === 'completed')
                                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="2.5" y="2.5" width="15" height="15" rx="3" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M6.5 10.2L9.1 12.8L13.8 7.8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                @elseif ($item['status'] === 'in_progress')
                                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="2.5" y="2.5" width="15" height="15" rx="3" stroke="currentColor" stroke-width="1.5" />
                                        <path d="M10 5.5V10L12.8 11.4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                @else
                                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="2.5" y="2.5" width="15" height="15" rx="3" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                @endif
                            </span>

                            <div class="finba-roadmap-item__content">
                                <div class="finba-roadmap-item__heading">
                                    <h3 class="finba-roadmap-item__title">
                                        {{ $item['title'] }}
                                    </h3>

                                    <span @class([
                                        'finba-roadmap-item__badge',
                                        'finba-roadmap-item__badge--' . $item['status'],
                                    ])>
                                        {{ $item['status_label'] }}
                                    </span>
                                </div>

                                @if (filled($item['description'] ?? null))
                                    <p class="finba-roadmap-item__description">
                                        {{ $item['description'] }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="finba-roadmap-empty">
                <p>Nenhum item publicado no roadmap por enquanto.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
