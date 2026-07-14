<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionEntryMode: string implements HasLabel
{
    case IMMEDIATE = 'immediate';
    case INSTALLMENT = 'installment';

    public function getLabel(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'À vista',
            self::INSTALLMENT => 'Parcelado',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->getLabel()])
            ->all();
    }
}
