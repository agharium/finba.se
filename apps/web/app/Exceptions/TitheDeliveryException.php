<?php

namespace App\Exceptions;

use Exception;

class TitheDeliveryException extends Exception
{
    public static function nothingPending(): self
    {
        return new self('Não há valores pendentes de entrega para este período.');
    }

    public static function nothingSelected(): self
    {
        return new self('Selecione ao menos um item para entregar.');
    }

    public static function itemNotPending(string $label): self
    {
        return new self("Não há {$label} pendente para entrega neste período.");
    }

    public static function notTither(): self
    {
        return new self('A entrega de dízimos está disponível apenas para dizimistas.');
    }
}
