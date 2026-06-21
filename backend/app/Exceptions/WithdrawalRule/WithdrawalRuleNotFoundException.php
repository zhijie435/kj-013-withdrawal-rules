<?php

namespace App\Exceptions\WithdrawalRule;

class WithdrawalRuleNotFoundException extends WithdrawalRuleException
{
    protected string $errorCode = 'WITHDRAWAL_RULE_NOT_FOUND';

    protected int $httpCode = 404;

    public static function for(string $userLevel, string $currency, string $method): self
    {
        return new self(
            "未找到适用于用户等级[{$userLevel}]、币种[{$currency}]、方式[{$method}]的提现规则",
            [
                'user_level' => $userLevel,
                'currency' => $currency,
                'method' => $method,
            ]
        );
    }

    public static function byId(int $id): self
    {
        return new self("提现规则不存在", ['id' => $id], 404);
    }
}
