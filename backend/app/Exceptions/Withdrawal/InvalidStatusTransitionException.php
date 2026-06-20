<?php

namespace App\Exceptions\Withdrawal;

class InvalidStatusTransitionException extends WithdrawalException
{
    protected int $errorCode = 42203;

    protected int $httpStatus = 422;

    public static function for(string $currentStatus, string $targetStatus): self
    {
        return new self(
            sprintf(
                '无法从状态 "%s" 转换到 "%s"',
            $currentStatus,
            $targetStatus
            )
        );
    }
}
