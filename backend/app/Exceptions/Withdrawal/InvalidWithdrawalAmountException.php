<?php

namespace App\Exceptions\Withdrawal;

class InvalidWithdrawalAmountException extends WithdrawalException
{
    protected int $errorCode = 42202;

    protected int $httpStatus = 422;
}
