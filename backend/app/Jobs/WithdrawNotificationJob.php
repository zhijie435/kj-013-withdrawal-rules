<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WithdrawNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Payment $payment,
        protected string $notificationType,
    ) {}

    public function handle(): void
    {
        if (!$this->payment->isWithdrawPrincipal()) {
            Log::warning('WithdrawNotificationJob: 不是提现记录', [
                'payment_id' => $this->payment->id,
                'type' => $this->payment->type,
            ]);
            return;
        }

        $distributor = $this->payment->distributor;
        if (!$distributor) {
            Log::warning('WithdrawNotificationJob: 未找到分销商', [
                'payment_id' => $this->payment->id,
            ]);
            return;
        }

        $message = match ($this->notificationType) {
            'submitted' => $this->getSubmittedMessage(),
            'approved' => $this->getApprovedMessage(),
            'rejected' => $this->getRejectedMessage(),
            default => null,
        };

        if (!$message) {
            Log::warning('WithdrawNotificationJob: 未知的通知类型', [
                'type' => $this->notificationType,
            ]);
            return;
        }

        Log::info('提现通知', [
            'payment_id' => $this->payment->id,
            'payment_no' => $this->payment->payment_no,
            'distributor_id' => $distributor->id,
            'distributor_name' => $distributor->name,
            'notification_type' => $this->notificationType,
            'message' => $message,
        ]);
    }

    protected function getSubmittedMessage(): string
    {
        return sprintf(
            '您的提现申请已提交，金额 ¥%s，%s',
            number_format($this->payment->amount, 2),
            $this->payment->isPending() ? '等待平台审核' : '即将到账'
        );
    }

    protected function getApprovedMessage(): string
    {
        return sprintf(
            '您的提现申请已通过，金额 ¥%s，预计 %s 个工作日内到账',
            number_format($this->payment->amount, 2),
            config('withdraw.processing_days', 3)
        );
    }

    protected function getRejectedMessage(): string
    {
        $reason = $this->payment->audit_remark ?: '未说明原因';
        return sprintf(
            '您的提现申请已被驳回，原因：%s',
            $reason
        );
    }
}
