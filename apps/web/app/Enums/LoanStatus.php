<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasLabel
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::CLOSED => 'Fechado',
        };
    }
}
