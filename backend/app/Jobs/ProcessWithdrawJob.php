<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\WithdrawService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Payment $payment,
        protected ?User $auditor = null,
    ) {}

    public function handle(WithdrawService $withdrawService): void
    {
        if (!$this->payment->isWithdrawPrincipal() || !$this->payment->isPending()) {
            Log::warning('ProcessWithdrawJob: 无效的提现记录', [
                'payment_id' => $this->payment->id,
                'type' => $this->payment->type,
                'status' => $this->payment->status,
            ]);
            return;
        }

        try {
            $auditor = $this->auditor ?? User::where('user_type', 'platform')->first();

            if (!$auditor) {
                Log::error('ProcessWithdrawJob: 未找到平台管理员作为审核人');
                return;
            }

            $withdrawService->approveWithdraw($this->payment, $auditor, [
                'remark' => '系统自动审核通过',
            ]);

            Log::info('ProcessWithdrawJob: 提现自动审核通过', [
                'payment_id' => $this->payment->id,
                'payment_no' => $this->payment->payment_no,
                'amount' => $this->payment->amount,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessWithdrawJob: 提现处理失败', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }
}
