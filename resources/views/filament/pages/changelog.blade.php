@php
    use App\Support\ApplicationBuild;
@endphp

<x-filament-panels::page>
    <div class="finba-changelog mx-auto w-full max-w-3xl">
        <section class="finba-changelog-summary" aria-label="Resumo da versão">
            <div class="finba-changelog-summary__brand">
                <h2 class="finba-changelog-summary__name">Finba.se</h2>
                <p class="finba-changelog-summary__version">{{ ApplicationBuild::displayVersion() }}</p>
            </div>

            <div class="finba-changelog-summary__status">
                <span class="finba-changelog-summary__status-label">Status</span>
                <span class="finba-changelog-summary__status-value">{{ ApplicationBuild::stage() }}</span>
            </div>

            <ul class="finba-changelog-summary__highlights">
                <li>✓ Produção</li>
                <li>✓ PWA instalável</li>
                <li>✓ Parcelamentos</li>
                <li>✓ Contas a receber</li>
                <li>✓ Feedback integrado</li>
            </ul>
        </section>

        @forelse ($this->getEntries() as $entry)
            <article class="finba-changelog-day" wire:key="changelog-day-{{ $entry['date'] }}">
                <div class="finba-changelog-day__marker" aria-hidden="true"></div>

                <div class="finba-changelog-day__content">
                    <header class="finba-changelog-day__header">
                        <div class="finba-changelog-day__meta">
                            <time class="finba-changelog-day__date" datetime="{{ $entry['date'] }}">
                                {{ $entry['formatted_date'] }}
                            </time>

                            <div class="finba-changelog-day__badges">
                                @if (filled($entry['version'] ?? null))
                                    <span class="finba-changelog-day__badge finba-changelog-day__badge--version">
                                        {{ $entry['display_version'] }}
                                    </span>
                                @endif

                                @if (filled($entry['badge'] ?? null))
                                    <span class="finba-changelog-day__badge finba-changelog-day__badge--status">
                                        {{ $entry['badge'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <h2 class="finba-changelog-day__title">
                            {{ $entry['title'] }}
                        </h2>
                    </header>

                    @foreach ($entry['groups'] as $group)
                        <section class="finba-changelog-group" wire:key="changelog-group-{{ $entry['date'] }}-{{ $group['title'] }}">
                            <h3 class="finba-changelog-group__title">
                                {{ $group['title'] }}
                            </h3>

                            <ul class="finba-changelog-entries">
                                @foreach ($group['items'] as $item)
                                    <li
                                        @class([
                                            'finba-changelog-entry',
                                            'finba-changelog-entry--' . $item['type'],
                                            'finba-changelog-entry--internal' => $item['is_internal'],
                                        ])
                                        wire:key="changelog-item-{{ $entry['date'] }}-{{ md5($item['text']) }}"
                                    >
                                        <span class="finba-changelog-entry__badge">
                                            {{ $item['type_label'] }}
                                        </span>

                                        <p class="finba-changelog-entry__text">
                                            {{ $item['text'] }}
                                        </p>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                </div>
            </article>
        @empty
            <div class="finba-changelog-empty">
                <p>Nenhuma entrada publicada no changelog por enquanto.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
