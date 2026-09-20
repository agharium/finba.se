<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommitmentOffsetUnit: string implements HasLabel
{
    case DAY = 'DAY';
    case WEEK = 'WEEK';
    case MONTH = 'MONTH';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DAY => 'Dia(s)',
            self::WEEK => 'Semana(s)',
            self::MONTH => 'Mês(es)',
        };
    }
}
