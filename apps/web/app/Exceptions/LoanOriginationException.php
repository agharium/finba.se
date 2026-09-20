<?php

namespace App\Exceptions;

use Exception;

class LoanOriginationException extends Exception
{
    public static function amountRequired(): self
    {
        return new self('Informe um valor válido para o empréstimo.');
    }

    public static function personRequired(): self
    {
        return new self('Selecione uma pessoa para o empréstimo.');
    }

    public static function unsupportedType(): self
    {
        return new self('Tipo de empréstimo inválido para origem.');
    }

    public static function invalidSchedule(): self
    {
        return new self('Informe um número válido de parcelas para o cronograma.');
    }
}
