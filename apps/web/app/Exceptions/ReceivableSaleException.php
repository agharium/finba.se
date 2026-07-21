<?php

namespace App\Exceptions;

use Exception;

class ReceivableSaleException extends Exception
{
    public static function featureUnavailable(): self
    {
        return new self('Vendas a prazo não estão habilitadas para este usuário.');
    }

    public static function personRequired(): self
    {
        return new self('Selecione uma pessoa para criar a conta a receber.');
    }

    public static function amountRequired(): self
    {
        return new self('Informe um valor válido para a venda a prazo.');
    }
}
