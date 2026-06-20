<?php

namespace App\Enums;

enum WithdrawAuditAction: string
{
    case SUBMIT = 'submit';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case PROCESS = 'process';
    case COMPLETE = 'complete';
    case FAIL = 'fail';
    case CANCEL = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::SUBMIT => '提交申请',
            self::APPROVE => '审核通过',
            self::REJECT => '审核驳回',
            self::PROCESS => '开始处理',
            self::COMPLETE => '提现完成',
            self::FAIL => '提现失败',
            self::CANCEL => '取消申请',
        };
    }

    public function fromStatus(): ?WithdrawStatus
    {
        return match ($this) {
            self::SUBMIT => null,
            self::APPROVE, self::REJECT, self::CANCEL => WithdrawStatus::PENDING,
            self::PROCESS, self::FAIL => WithdrawStatus::APPROVED,
            self::COMPLETE => WithdrawStatus::PROCESSING,
        };
    }

    public function toStatus(): WithdrawStatus
    {
        return match ($this) {
            self::SUBMIT => WithdrawStatus::PENDING,
            self::APPROVE => WithdrawStatus::APPROVED,
            self::REJECT => WithdrawStatus::REJECTED,
            self::PROCESS => WithdrawStatus::PROCESSING,
            self::COMPLETE => WithdrawStatus::COMPLETED,
            self::FAIL => WithdrawStatus::FAILED,
            self::CANCEL => WithdrawStatus::CANCELLED,
        };
    }
}
