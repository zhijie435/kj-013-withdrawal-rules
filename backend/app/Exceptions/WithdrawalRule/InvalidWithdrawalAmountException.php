<?php

namespace App\Exceptions\WithdrawalRule;

class InvalidWithdrawalAmountException extends WithdrawalRuleException
{
    protected string $errorCode = 'WITHDRAWAL_AMOUNT_INVALID';

    protected int $httpCode = 422;

    public static function belowMinimum(float $amount, float $minAmount, string $currency): self
    {
        return new self(
            "提现金额不能低于最低限额: {$minAmount} {$currency}",
            [
                'amount' => $amount,
                'min_amount' => $minAmount,
                'currency' => $currency,
                'type' => 'below_minimum',
            ]
        );
    }

    public static function aboveMaximum(float $amount, float $maxAmount, string $currency): self
    {
        return new self(
            "提现金额不能超过最高限额: {$maxAmount} {$currency}",
            [
                'amount' => $amount,
                'max_amount' => $maxAmount,
                'currency' => $currency,
                'type' => 'above_maximum',
            ]
        );
    }

    public static function zeroActualAmount(): self
    {
        return new self('实际到账金额必须大于0', ['type' => 'zero_actual']);
    }
}
