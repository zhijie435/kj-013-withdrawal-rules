<?php

namespace App\Exceptions\WithdrawalRule;

class WithdrawalRuleConflictException extends WithdrawalRuleException
{
    protected string $errorCode = 'WITHDRAWAL_RULE_CONFLICT';

    protected int $httpCode = 409;

    public static function codeExists(string $code): self
    {
        return new self("规则编码 [{$code}] 已存在", ['code' => $code]);
    }

    public static function ruleExists(string $userLevel, string $currency, string $method): self
    {
        return new self(
            "该用户等级[{$userLevel}]、币种[{$currency}]、方式[{$method}]的规则已存在",
            [
                'user_level' => $userLevel,
                'currency' => $currency,
                'method' => $method,
            ]
        );
    }
}
