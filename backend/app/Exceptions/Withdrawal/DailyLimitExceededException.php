<?php

namespace App\Exceptions\Withdrawal;

class DailyLimitExceededException extends WithdrawalException
{
    protected int $errorCode = 42205;

    protected int $httpStatus = 422;
}
