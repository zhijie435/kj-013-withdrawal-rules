<?php

namespace App\Exceptions\Withdrawal;

class WithdrawalRuleNotFoundException extends WithdrawalException
{
    protected int $errorCode = 42204;

    protected int $httpStatus = 422;
}
