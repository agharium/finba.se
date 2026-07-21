<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncomePaymentMode: string implements HasLabel
{
    case NOW = 'now';
    case LATER = 'later';

    public function getLabel(): string
    {
        return match ($this) {
            self::NOW => 'Recebido agora',
            self::LATER => 'Receber depois',
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
