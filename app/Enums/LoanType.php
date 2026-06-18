<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LoanType: string implements HasLabel
{
    case LENT = 'LENT';
    case BORROWED = 'BORROWED';

    public function getLabel(): string
    {
        return match ($this) {
            self::LENT => 'Emprestei',
            self::BORROWED => 'Peguei emprestado',
        };
    }
}
