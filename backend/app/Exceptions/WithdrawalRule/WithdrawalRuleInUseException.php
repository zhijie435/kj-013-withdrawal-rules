<?php

namespace App\Exceptions\WithdrawalRule;

class WithdrawalRuleInUseException extends WithdrawalRuleException
{
    protected string $errorCode = 'WITHDRAWAL_RULE_IN_USE';

    protected int $httpCode = 422;

    public static function hasWithdrawals(int $ruleId, int $count): self
    {
        return new self(
            "该规则下存在 {$count} 条提现记录，无法删除",
            [
                'rule_id' => $ruleId,
                'withdrawal_count' => $count,
            ]
        );
    }
}
