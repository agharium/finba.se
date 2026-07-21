<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Helpers
{
    public static function filamentFilterValue(mixed $livewire, string $filter): ?string
    {
        if (! $livewire) {
            return null;
        }

        $value = data_get($livewire, "tableFilters.{$filter}.value");

        if ($value === null) {
            $value = data_get($livewire, "tableFilters.{$filter}");
        }

        if (is_array($value)) {
            $value = $value['value'] ?? null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        return filled($value) ? (string) $value : null;
    }

    public static function monthLabelPtBr(int $month): string
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ][$month] ?? (string) $month;
    }

    public static function whereUnaccentedLike(Builder $query, string $column, string $search): Builder
    {
        $normalizedSearch = strtolower(Str::ascii($search));

        $normalizedColumn = collect([
            ['á', 'a'],
            ['à', 'a'],
            ['ã', 'a'],
            ['â', 'a'],
            ['ä', 'a'],
            ['é', 'e'],
            ['è', 'e'],
            ['ê', 'e'],
            ['ë', 'e'],
            ['í', 'i'],
            ['ì', 'i'],
            ['î', 'i'],
            ['ï', 'i'],
            ['ó', 'o'],
            ['ò', 'o'],
            ['õ', 'o'],
            ['ô', 'o'],
            ['ö', 'o'],
            ['ú', 'u'],
            ['ù', 'u'],
            ['û', 'u'],
            ['ü', 'u'],
            ['ç', 'c'],
            ['ñ', 'n'],
        ])->reduce(
            fn (string $expression, array $replacement): string => sprintf(
                "replace(%s, '%s', '%s')",
                $expression,
                $replacement[0],
                $replacement[1],
            ),
            "lower(coalesce({$column}, ''))",
        );

        return $query->whereRaw("{$normalizedColumn} like ?", ["%{$normalizedSearch}%"]);
    }
}
