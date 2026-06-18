<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstallmentGroupStatus: string implements HasLabel
{
    case ACTIVE = 'ACTIVE';
    case PAID = 'PAID';
    case CANCELED = 'CANCELED';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Ativo',
            self::PAID => 'Pago',
            self::CANCELED => 'Cancelado',
        };
    }
}
