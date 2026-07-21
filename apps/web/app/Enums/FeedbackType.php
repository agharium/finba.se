<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeedbackType: string implements HasLabel
{
    case BUG = 'BUG';
    case SUGGESTION = 'SUGGESTION';
    case OTHER = 'OTHER';

    public function getLabel(): string
    {
        return match ($this) {
            self::BUG => 'Problema',
            self::SUGGESTION => 'Sugestão',
            self::OTHER => 'Outro',
        };
    }
}
