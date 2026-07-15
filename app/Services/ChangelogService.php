<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ChangelogService
{
    public const ITEM_TYPE_ORDER = ['added', 'changed', 'fixed', 'removed', 'decision', 'internal'];

    public function entries(?User $user = null): Collection
    {
        $user ??= auth()->user();
        $locale = $user instanceof User
            ? app(LocationDefaultsService::class)->getLocale($user)
            : app(LocationDefaultsService::class)->inferLocale();

        return $this->sortedEntries($this->rawEntries())
            ->map(fn (array $entry): array => $this->presentEntry($entry, $locale));
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
        $carbonLocale = str_starts_with($locale, 'pt') ? 'pt_BR' : 'en';

        return Carbon::parse($date)
            ->locale($carbonLocale)
            ->translatedFormat('d \d\e F \d\e Y');
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function presentEntry(array $entry, string $locale): array
    {
        $version = filled($entry['version'] ?? null) ? (string) $entry['version'] : null;

        return [
            ...$entry,
            'formatted_date' => $this->formatEntryDate((string) $entry['date'], $locale),
            'display_version' => $version !== null
                ? (str_starts_with($version, 'v') ? $version : 'v'.$version)
                : null,
            'groups' => collect($entry['groups'] ?? [])
                ->map(fn (array $group): array => [
                    ...$group,
                    'items' => collect($group['items'] ?? [])
                        ->map(fn (array $item): array => [
                            ...$item,
                            'type_label' => $this->itemTypeLabel((string) ($item['type'] ?? '')),
                            'is_internal' => $this->isInternalType((string) ($item['type'] ?? '')),
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
