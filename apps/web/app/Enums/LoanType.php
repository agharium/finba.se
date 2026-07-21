<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanType: string implements HasLabel
{
    case LENT = 'LENT';
    case BORROWED = 'BORROWED';
    case RECEIVABLE = 'RECEIVABLE';

    public function getLabel(): string
    {
        return match ($this) {
            self::LENT => 'Empréstimo concedido',
            self::BORROWED => 'Dívida',
            self::RECEIVABLE => 'Conta a receber',
        };
    }
}
