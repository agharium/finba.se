<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Purpose: string implements HasLabel
{
    case TITHE = 'TITHE';
    case OFFERING = 'OFFERING';
    case FIRSTFRUITS = 'FIRSTFRUITS';

    public function getLabel(): string
    {
        return match ($this) {
            self::TITHE => 'Dízimo',
            self::OFFERING => 'Oferta',
            self::FIRSTFRUITS => 'Primícias',
        };
    }
}