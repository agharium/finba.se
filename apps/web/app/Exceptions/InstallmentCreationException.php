<?php

namespace App\Exceptions;

use RuntimeException;

class InstallmentCreationException extends RuntimeException
{
    public static function invalidCount(): self
    {
        return new self('Informe entre 2 e 120 parcelas.');
    }

    public static function invalidAmount(): self
    {
        return new self('O valor total deve ser maior que zero.');
    }

    public static function firstDateRequired(): self
    {
        return new self('Informe a data da primeira parcela.');
    }

    public static function typeRequired(): self
    {
        return new self('Informe o tipo da transação.');
    }

    public static function foreignOwnership(): self
    {
        return new self('Categoria, pessoa ou cidade inválida para este usuário.');
    }
}
