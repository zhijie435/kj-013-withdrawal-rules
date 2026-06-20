<?php

namespace App\Exceptions\Withdrawal;

class InsufficientBalanceException extends WithdrawalException
{
    protected int $errorCode = 42201;

    protected int $httpStatus = 422;
}
