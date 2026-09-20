<?php

namespace App\Exceptions;

use Exception;

class LoanPaymentException extends Exception
{
    public static function loanNotFound(): self
    {
        return new self('Empréstimo não encontrado.');
    }

    public static function loanNotOpen(): self
    {
        return new self('Este empréstimo já foi encerrado.');
    }

    public static function amountRequired(): self
    {
        return new self('Informe um valor válido para o pagamento.');
    }

    public static function unsupportedLoanType(): self
    {
        return new self('Tipo de empréstimo não suportado para este fluxo de pagamento.');
    }

    public static function overpayment(): self
    {
        return new self('O valor informado excede o saldo em aberto do empréstimo.');
    }

    public static function commitmentNotFound(): self
    {
        return new self('Compromisso não encontrado para este empréstimo.');
    }
}
