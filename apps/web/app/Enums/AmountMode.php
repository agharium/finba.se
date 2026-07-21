<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AmountMode: string implements HasLabel
{
    case FIXED = 'FIXED';
    case VARIABLE = 'VARIABLE';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED => 'Fixo',
            self::VARIABLE => 'Variável',
        };
    }
}