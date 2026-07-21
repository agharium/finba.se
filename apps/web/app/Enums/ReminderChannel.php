<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReminderChannel: string implements HasLabel
{
    case EMAIL = 'EMAIL';
    case WHATSAPP = 'WHATSAPP';
    case PUSH = 'PUSH';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::EMAIL => 'E-mail',
            self::WHATSAPP => 'WhatsApp',
            self::PUSH => 'Push',
        };
    }
}
