<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionType: string implements HasLabel
{
    case INCOME = 'INCOME';
    case EXPENSE = 'EXPENSE';

    public function getLabel(): string
    {
        return match ($this) {
            self::INCOME => 'Receita',
            self::EXPENSE => 'Despesa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::INCOME => 'success',
            self::EXPENSE => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::INCOME => 'heroicon-m-arrow-trending-up',
            self::EXPENSE => 'heroicon-m-arrow-trending-down',
        };
    }

    public static function fromState(self|string|null $state): ?self
    {
        if ($state instanceof self) {
            return $state;
        }

        return filled($state) ? self::tryFrom($state) : null;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getLabel()])
            ->all();
    }

    public static function colors(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getColor()])
            ->all();
    }

    public static function icons(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->getIcon()])
            ->all();
    }

    public static function labelFor(self|string|null $state, string $default = '-'): string
    {
        return self::fromState($state)?->getLabel() ?? $default;
    }

    public static function colorFor(self|string|null $state, string $default = 'gray'): string
    {
        return self::fromState($state)?->getColor() ?? $default;
    }

    public static function iconFor(self|string|null $state, string $default = 'heroicon-m-question-mark-circle'): string
    {
        return self::fromState($state)?->getIcon() ?? $default;
    }

    public static function listLabel(array|string|null $state): string
    {
        $types = self::valuesFromState($state);

        $hasIncome = in_array(self::INCOME->value, $types, true);
        $hasExpense = in_array(self::EXPENSE->value, $types, true);

        return match (true) {
            $hasIncome && $hasExpense => 'Receita + Despesa',
            $hasIncome => self::INCOME->getLabel(),
            $hasExpense => self::EXPENSE->getLabel(),
            default => '-',
        };
    }

    public static function listColor(array|string|null $state): string
    {
        $types = self::valuesFromState($state);

        $hasIncome = in_array(self::INCOME->value, $types, true);
        $hasExpense = in_array(self::EXPENSE->value, $types, true);

        return match (true) {
            $hasIncome && $hasExpense => 'info',
            $hasIncome => self::INCOME->getColor(),
            $hasExpense => self::EXPENSE->getColor(),
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    private static function valuesFromState(array|string|null $state): array
    {
        return collect(is_array($state) ? $state : [$state])
            ->filter()
            ->map(fn (mixed $type): ?string => $type instanceof self ? $type->value : (is_string($type) ? $type : null))
            ->filter()
            ->values()
            ->all();
    }
}
