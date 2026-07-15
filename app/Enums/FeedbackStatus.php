<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeedbackStatus: string implements HasLabel
{
    case OPEN = 'OPEN';
    case REVIEWING = 'REVIEWING';
    case RESOLVED = 'RESOLVED';
    case DISMISSED = 'DISMISSED';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Aberto',
            self::REVIEWING => 'Em análise',
            self::RESOLVED => 'Resolvido',
            self::DISMISSED => 'Descartado',
        };
    }
}
