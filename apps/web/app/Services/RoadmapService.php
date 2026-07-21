<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RoadmapService
{
    public const SUPPORTED_STATUSES = ['completed', 'in_progress', 'planned'];

    public const STATUS_ORDER = ['completed', 'in_progress', 'planned'];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categories(): Collection
    {
        return collect($this->rawCategories())
            ->map(fn (array $category): array => $this->presentCategory($category));
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = [
            'completed' => 0,
            'in_progress' => 0,
            'planned' => 0,
        ];

        foreach ($this->categories() as $category) {
            foreach ($category['items'] as $item) {
                $status = (string) $item['status'];
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function statusLabel(string $status): string
    {
        return match ($this->normalizeStatus($status)) {
            'completed' => 'Concluído',
            'in_progress' => 'Em desenvolvimento',
            'planned' => 'Planejado',
            default => 'Planejado',
        };
    }

    public function isSupportedStatus(string $status): bool
    {
        return in_array($status, self::SUPPORTED_STATUSES, true);
    }

    public function normalizeStatus(string $status): string
    {
        return $this->isSupportedStatus($status) ? $status : 'planned';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rawCategories(): array
    {
        $path = resource_path('data/roadmap.php');

        if (! is_file($path)) {
            return [];
        }

        $categories = require $path;

        return is_array($categories) ? $categories : [];
    }

    /**
     * @param  array<string, mixed>  $category
     * @return array<string, mixed>
     */
    private function presentCategory(array $category): array
    {
        $items = collect($category['items'] ?? [])
            ->map(fn (array $item): array => $this->presentItem($item))
            ->sortBy(fn (array $item): int => array_search($item['status'], self::STATUS_ORDER, true) ?: 99)
            ->values()
            ->all();

        return [
            ...$category,
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function presentItem(array $item): array
    {
        $status = $this->normalizeStatus((string) ($item['status'] ?? ''));

        return [
            ...$item,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
        ];
    }
}
