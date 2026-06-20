<?php

namespace App\Enums;

enum PaymentType: string
{
    case ESCROW_DEPOSIT = 'escrow_deposit';
    case ESCROW_RELEASE = 'escrow_release';
    case PLATFORM_FEE = 'platform_fee';
    case REFUND = 'refund';
    case RECHARGE = 'recharge';
    case WITHDRAW = 'withdraw';
    case WITHDRAW_FEE = 'withdraw_fee';

    public function label(): string
    {
        return match ($this) {
            self::ESCROW_DEPOSIT => '托管存款',
            self::ESCROW_RELEASE => '托管释放',
            self::PLATFORM_FEE => '平台费用',
            self::REFUND => '退款',
            self::RECHARGE => '余额充值',
            self::WITHDRAW => '余额提现',
            self::WITHDRAW_FEE => '提现手续费',
        };
    }

    public function isIncome(): bool
    {
        return in_array($this, [self::ESCROW_DEPOSIT, self::PLATFORM_FEE, self::RECHARGE, self::WITHDRAW_FEE], true);
    }

    public function isExpense(): bool
    {
        return in_array($this, [self::ESCROW_RELEASE, self::REFUND, self::WITHDRAW], true);
    }

    public function affectsOrderPaymentStatus(): bool
    {
        return in_array($this, [self::ESCROW_DEPOSIT, self::REFUND], true);
    }

    public function isRecharge(): bool
    {
        return $this === self::RECHARGE;
    }

    public function isWithdraw(): bool
    {
        return in_array($this, [self::WITHDRAW, self::WITHDRAW_FEE], true);
    }

    public function isWithdrawPrincipal(): bool
    {
        return $this === self::WITHDRAW;
    }

    public function isWithdrawFee(): bool
    {
        return $this === self::WITHDRAW_FEE;
    }
}
