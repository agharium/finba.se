<?php

namespace App\Exceptions;

use Exception;

class CommitmentAllocationException extends Exception
{
    public static function amountRequired(): self
    {
        return new self('Informe um valor válido para a alocação.');
    }

    public static function exceedsCommitmentRemaining(): self
    {
        return new self('A alocação excede o valor restante do compromisso.');
    }

    public static function exceedsTransactionUnallocated(): self
    {
        return new self('A alocação excede o valor ainda não alocado da transação.');
    }

    public static function ownershipMismatch(): self
    {
        return new self('Compromisso e transação devem pertencer ao mesmo usuário.');
    }

    public static function loanMismatch(): self
    {
        return new self('A transação e o compromisso devem estar vinculados ao mesmo empréstimo.');
    }
}
