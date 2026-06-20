<?php

namespace App\Exceptions\Withdrawal;

class WalletNotFoundException extends WithdrawalException
{
    protected int $errorCode = 42207;

    protected int $httpStatus = 422;
}
