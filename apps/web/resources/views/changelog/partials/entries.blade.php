{{-- Shared changelog day list (Filament + public). Expects $entries collection. --}}
@forelse ($entries as $entry)
    <article
        @class([
            'finba-changelog-day',
            'finba-changelog-day--featured' => $entry['featured'] ?? false,
        ])
        @if (! empty($entry['featured']))
            data-featured="true"
        @endif
        wire:key="changelog-day-{{ $entry['date'] }}"
    >
        <div class="finba-changelog-day__marker" aria-hidden="true"></div>

        <div class="finba-changelog-day__content">
            <header class="finba-changelog-day__header">
                <div class="finba-changelog-day__meta">
                    <time class="finba-changelog-day__date" datetime="{{ $entry['date'] }}">
                        {{ $entry['formatted_date'] }}
                    </time>

                    <div class="finba-changelog-day__badges">
                        @if (! empty($entry['featured']))
                            <span class="finba-changelog-day__badge finba-changelog-day__badge--featured">
                                {{ $entry['featured_label'] ?? 'Marco arquitetural' }}
                            </span>
                        @endif

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

                @if (filled($entry['featured_summary'] ?? null))
                    <p class="finba-changelog-day__lede">
                        {{ $entry['featured_summary'] }}
                    </p>
                @endif
            </header>

            @foreach ($entry['groups'] as $group)
                <section class="finba-changelog-group">
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
