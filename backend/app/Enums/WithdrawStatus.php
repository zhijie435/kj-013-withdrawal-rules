<?php

namespace App\Enums;

enum WithdrawStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::APPROVED => '审核通过',
            self::PROCESSING => '处理中',
            self::COMPLETED => '已完成',
            self::REJECTED => '已驳回',
            self::FAILED => '提现失败',
            self::CANCELLED => '已取消',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::PROCESSING => 'primary',
            self::COMPLETED => 'success',
            self::REJECTED, self::FAILED, self::CANCELLED => 'danger',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isSuccess(): bool
    {
        return in_array($this, [self::APPROVED, self::PROCESSING, self::COMPLETED], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::REJECTED, self::FAILED, self::CANCELLED], true);
    }

    public function canAudit(): bool
    {
        return $this === self::PENDING;
    }

    public function canCancel(): bool
    {
        return $this === self::PENDING;
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target->value, $this->allowedTransitionValues(), true);
    }

    public function allowedTransitions(): array
    {
        return array_map(
            fn (string $value) => self::from($value),
            $this->allowedTransitionValues()
        );
    }

    protected function allowedTransitionValues(): array
    {
        $transitions = [
            self::PENDING->value => [
                self::APPROVED->value,
                self::REJECTED->value,
                self::CANCELLED->value,
            ],
            self::APPROVED->value => [
                self::PROCESSING->value,
                self::COMPLETED->value,
                self::FAILED->value,
            ],
            self::PROCESSING->value => [
                self::COMPLETED->value,
                self::FAILED->value,
            ],
            self::COMPLETED->value => [],
            self::REJECTED->value => [],
            self::FAILED->value => [],
            self::CANCELLED->value => [],
        ];

        return $transitions[$this->value] ?? [];
    }

    public function isTerminal(): bool
    {
        return $this->isFinal();
    }
}
