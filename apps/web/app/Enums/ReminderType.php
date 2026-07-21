<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReminderType: string implements HasLabel
{
    case ANNIVERSARY = 'ANNIVERSARY';
    case LOAN = 'LOAN';
    case COMMITMENT = 'COMMITMENT';
    case CUSTOM = 'CUSTOM';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ANNIVERSARY => 'Aniversário',
            self::LOAN => 'Empréstimo / dívida',
            self::COMMITMENT => 'Compromisso recorrente',
            self::CUSTOM => 'Personalizado',
        };
    }
}