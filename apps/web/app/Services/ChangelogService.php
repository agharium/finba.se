<?php

namespace App\Services;

use App\Enums\ChangelogVisibility;
use App\Enums\Locale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChangelogService
{
    public const ITEM_TYPE_ORDER = ['added', 'changed', 'fixed', 'removed', 'decision', 'internal'];

    /**
     * Entries for the authenticated Filament changelog (all intended content).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entries(?User $user = null): Collection
    {
        return $this->presentForAudience($user, authenticated: true);
    }

    /**
     * Entries safe for the public, unauthenticated changelog page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function publicEntries(?User $user = null): Collection
    {
        return $this->presentForAudience($user, authenticated: false);
    }

    /**
     * @deprecated Use entries() instead.
     */
    public function releases(?User $user = null): Collection
    {
        return $this->entries($user);
    }

    public function latestEntry(?User $user = null): ?array
    {
        return $this->entries($user)->first();
    }

    public function latestPublicEntry(?User $user = null): ?array
    {
        return $this->publicEntries($user)->first();
    }

    public function latestVersion(?User $user = null): ?string
    {
        return $this->latestEntry($user)['version'] ?? null;
    }

    public function itemTypeLabel(string $type): string
    {
        return match ($type) {
            'added' => 'Adicionado',
            'changed' => 'Alterado',
            'fixed' => 'Corrigido',
            'removed' => 'Removido',
            'decision' => 'Decisão',
            'internal' => 'Interno',
            default => ucfirst($type),
        };
    }

    public function isInternalType(string $type): bool
    {
        return $type === 'internal';
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return Collection<int, array<string, mixed>>
     */
    public function sortedEntries(array $entries): Collection
    {
        return collect($entries)
            ->sortByDesc('date')
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rawEntries(): array
    {
        $path = resource_path('data/changelog.php');

        if (! is_file($path)) {
            return [];
        }

        $entries = require $path;

        return is_array($entries) ? $entries : [];
    }

    public function earliestDate(): ?string
    {
        return $this->sortedEntries($this->rawEntries())->last()['date'] ?? null;
    }

    public function latestDate(): ?string
    {
        return $this->sortedEntries($this->rawEntries())->first()['date'] ?? null;
    }

    public function formatEntryDate(string $date, string $locale): string
    {
        $resolved = Locale::fromNullable($locale);

        return Carbon::parse($date)
            ->locale($resolved->carbonLocale())
            ->translatedFormat($resolved->longDateFormat());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function presentForAudience(?User $user, bool $authenticated): Collection
    {
        $user ??= auth()->user();
        $locale = $user instanceof User
            ? app(LocationDefaultsService::class)->getLocale($user)
            : app(LocationDefaultsService::class)->inferLocale();

        return $this->sortedEntries($this->rawEntries())
            ->map(fn (array $entry): ?array => $this->presentEntry($entry, $locale, $authenticated))
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function presentEntry(array $entry, string $locale, bool $authenticated): ?array
    {
        $entryVisibility = ChangelogVisibility::fromMixed($entry['visibility'] ?? null);

        if (! $authenticated && ! $entryVisibility->isVisibleToPublic()) {
            return null;
        }

        $version = filled($entry['version'] ?? null) ? (string) $entry['version'] : null;
        $featured = (bool) ($entry['featured'] ?? false);
        $featuredLabel = filled($entry['featured_label'] ?? null)
            ? (string) $entry['featured_label']
            : ($featured ? 'Marco arquitetural' : null);
        $featuredSummary = filled($entry['featured_summary'] ?? null)
            ? (string) $entry['featured_summary']
            : null;

        $groups = collect($entry['groups'] ?? [])
            ->map(function (array $group) use ($authenticated, $entryVisibility): ?array {
                $items = collect($group['items'] ?? [])
                    ->map(function (array $item) use ($authenticated, $entryVisibility): ?array {
                        $itemVisibility = ChangelogVisibility::fromMixed(
                            $item['visibility'] ?? $entryVisibility->value
                        );

                        if (! $authenticated && ! $itemVisibility->isVisibleToPublic()) {
                            return null;
                        }

                        $type = (string) ($item['type'] ?? '');

                        return [
                            ...$item,
                            'visibility' => $itemVisibility->value,
                            'type_label' => $this->itemTypeLabel($type),
                            'is_internal' => $this->isInternalType($type),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                return [
                    ...$group,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($groups === []) {
            return null;
        }

        return [
            ...$entry,
            'featured' => $featured,
            'featured_label' => $featuredLabel,
            'featured_summary' => $featuredSummary,
            'visibility' => $entryVisibility->value,
            'formatted_date' => $this->formatEntryDate((string) $entry['date'], $locale),
            'display_version' => $version !== null
                ? (str_starts_with($version, 'v') ? $version : 'v'.$version)
                : null,
            'groups' => $groups,
        ];
    }
}
