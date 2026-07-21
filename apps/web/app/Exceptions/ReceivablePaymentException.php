<?php

namespace App\Exceptions;

use Exception;

class ReceivablePaymentException extends Exception
{
    public static function loanNotFound(): self
    {
        return new self('Conta a receber não encontrada.');
    }

    public static function invalidLoanType(): self
    {
        return new self('A transação não está vinculada a uma conta a receber válida.');
    }

    public static function loanNotOpen(): self
    {
        return new self('Esta conta a receber já foi encerrada.');
    }

    public static function amountRequired(): self
    {
        return new self('Informe um valor válido para o recebimento.');
    }

    public static function overpayment(): self
    {
        return new self('O valor informado excede o saldo em aberto da conta a receber.');
    }
}
