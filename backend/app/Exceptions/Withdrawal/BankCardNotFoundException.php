<?php

namespace App\Exceptions\Withdrawal;

class BankCardNotFoundException extends WithdrawalException
{
    protected int $errorCode = 42206;

    protected int $httpStatus = 422;
}
