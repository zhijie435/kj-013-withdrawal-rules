<?php

namespace App\Exceptions;

class WithdrawException extends BusinessException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'WITHDRAW_ERROR';

    public static function disabled(): self
    {
        return self::withCode('提现功能暂未开放', 'WITHDRAW_DISABLED');
    }

    public static function belowMin(float $minAmount): self
    {
        return self::withCode(
            "提现金额不能低于最低限额 ¥{$minAmount}",
            'WITHDRAW_BELOW_MIN',
            ['min_amount' => $minAmount]
        );
    }

    public static function aboveMax(float $maxAmount): self
    {
        return self::withCode(
            "提现金额不能超过最高限额 ¥{$maxAmount}",
            'WITHDRAW_ABOVE_MAX',
            ['max_amount' => $maxAmount]
        );
    }

    public static function insufficientBalance(float $currentBalance, float $minBalanceKeep): self
    {
        return self::withCode(
            '余额不足，需保留最低余额',
            'WITHDRAW_INSUFFICIENT_BALANCE',
            [
                'current_balance' => $currentBalance,
                'min_balance_keep' => $minBalanceKeep,
                'available' => max(0, $currentBalance - $minBalanceKeep),
            ]
        );
    }

    public static function dailyLimitExceeded(float $dailyLimit, float $todayWithdrawn): self
    {
        return self::withCode(
            '今日已超出每日提现限额',
            'WITHDRAW_DAILY_LIMIT_EXCEEDED',
            [
                'daily_limit' => $dailyLimit,
                'today_withdrawn' => $todayWithdrawn,
                'remaining' => max(0, $dailyLimit - $todayWithdrawn),
            ]
        );
    }

    public static function monthlyLimitExceeded(float $monthlyLimit, float $monthWithdrawn): self
    {
        return self::withCode(
            '本月已超出每月提现限额',
            'WITHDRAW_MONTHLY_LIMIT_EXCEEDED',
            [
                'monthly_limit' => $monthlyLimit,
                'month_withdrawn' => $monthWithdrawn,
                'remaining' => max(0, $monthlyLimit - $monthWithdrawn),
            ]
        );
    }

    public static function invalidMethod(array $allowedMethods): self
    {
        return self::withCode(
            '不支持的提现方式',
            'WITHDRAW_METHOD_INVALID',
            ['allowed_methods' => $allowedMethods]
        );
    }

    public static function notWithdrawRecord(): self
    {
        return self::withCode('该记录不是提现申请', 'NOT_WITHDRAW_RECORD');
    }

    public static function notPending(): self
    {
        return self::withCode('该提现申请状态不允许审核', 'WITHDRAW_NOT_PENDING');
    }

    public static function distributorRequired(): self
    {
        return self::withCode('请指定提现的分销商', 'DISTRIBUTOR_REQUIRED');
    }
}
