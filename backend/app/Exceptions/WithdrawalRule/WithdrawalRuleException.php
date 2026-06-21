<?php

namespace App\Exceptions\WithdrawalRule;

use App\Exceptions\BusinessException;

class WithdrawalRuleException extends BusinessException
{
    protected string $errorCode = 'WITHDRAWAL_RULE_ERROR';

    protected int $httpCode = 422;

    public function __construct(string $message, array $details = [], ?int $httpCode = null)
    {
        parent::__construct($message, $details, $httpCode ?? $this->httpCode);
    }
}
