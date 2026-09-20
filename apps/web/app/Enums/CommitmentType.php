<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommitmentType: string implements HasLabel
{
    case LOAN = 'LOAN';
    case RECURRING = 'COMMITMENT';
    case CUSTOM = 'CUSTOM';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LOAN => 'Empréstimo / dívida',
            self::RECURRING => 'Compromisso recorrente',
            self::CUSTOM => 'Personalizado',
        };
    }
}
