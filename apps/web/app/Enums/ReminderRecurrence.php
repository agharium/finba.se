<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReminderRecurrence: string implements HasLabel
{
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';
    case YEARLY = 'YEARLY';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WEEKLY => 'Semanal',
            self::MONTHLY => 'Mensal',
            self::YEARLY => 'Anual',
        };
    }
}