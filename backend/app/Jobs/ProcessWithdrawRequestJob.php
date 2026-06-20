<?php

namespace App\Jobs;

use App\Enums\WithdrawStatus;
use App\Models\User;
use App\Models\WithdrawRequest;
use App\Services\WithdrawRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected WithdrawRequest $withdraw,
        protected string $action = 'process',
        protected ?User $operator = null,
        protected array $data = []
    ) {}

    public function handle(WithdrawRequestService $service): void
    {
        if (!$this->withdraw->exists) {
            Log::warning('ProcessWithdrawRequestJob: 提现记录不存在', [
                'withdraw_id' => $this->withdraw->id ?? null,
            ]);
            return;
        }

        try {
            $operator = $this->operator ?? User::where('user_type', 'platform')->first();

            if (!$operator) {
                Log::error('ProcessWithdrawRequestJob: 未找到平台操作员');
                return;
            }

            switch ($this->action) {
                case 'process':
                    $this->process($service, $operator);
                    break;
                case 'complete':
                    $this->complete($service, $operator);
                    break;
                case 'auto_audit':
                    $this->autoAudit($service, $operator);
                    break;
                default:
                    Log::warning('ProcessWithdrawRequestJob: 未知的操作类型', [
                        'action' => $this->action,
                        'withdraw_id' => $this->withdraw->id,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('ProcessWithdrawRequestJob: 提现处理失败', [
                'withdraw_id' => $this->withdraw->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }

    protected function process(WithdrawRequestService $service, User $operator): void
    {
        if (!$this->withdraw->canTransitionTo(WithdrawStatus::PROCESSING)) {
            Log::warning('ProcessWithdrawRequestJob: 状态不允许开始处理', [
                'withdraw_id' => $this->withdraw->id,
                'status' => $this->withdraw->status?->value,
            ]);
            return;
        }

        $service->process($this->withdraw, $operator, $this->data);

        Log::info('ProcessWithdrawRequestJob: 开始打款处理', [
            'withdraw_id' => $this->withdraw->id,
            'request_no' => $this->withdraw->request_no,
            'amount' => $this->withdraw->amount,
        ]);

        ProcessWithdrawRequestJob::dispatch($this->withdraw, 'complete', $operator, $this->data)
            ->delay(now()->addSeconds(30));
    }

    protected function complete(WithdrawRequestService $service, User $operator): void
    {
        if (!$this->withdraw->canTransitionTo(WithdrawStatus::COMPLETED)) {
            Log::warning('ProcessWithdrawRequestJob: 状态不允许完成', [
                'withdraw_id' => $this->withdraw->id,
                'status' => $this->withdraw->status?->value,
            ]);
            return;
        }

        $service->complete($this->withdraw, $operator, $this->data);

        Log::info('ProcessWithdrawRequestJob: 提现打款完成', [
            'withdraw_id' => $this->withdraw->id,
            'request_no' => $this->withdraw->request_no,
            'amount' => $this->withdraw->amount,
            'actual_amount' => $this->withdraw->actual_amount,
        ]);
    }

    protected function autoAudit(WithdrawRequestService $service, User $operator): void
    {
        if (!$this->withdraw->canAudit()) {
            Log::warning('ProcessWithdrawRequestJob: 状态不允许审核', [
                'withdraw_id' => $this->withdraw->id,
                'status' => $this->withdraw->status?->value,
            ]);
            return;
        }

        $rule = $this->withdraw->rule;

        if ($rule && !$rule->requires_audit) {
            $service->approve($this->withdraw, $operator, '系统自动审核通过');

            Log::info('ProcessWithdrawRequestJob: 自动审核通过', [
                'withdraw_id' => $this->withdraw->id,
                'request_no' => $this->withdraw->request_no,
                'amount' => $this->withdraw->amount,
            ]);

            ProcessWithdrawRequestJob::dispatch($this->withdraw, 'process', $operator, $this->data)
                ->delay(now()->addSeconds(5));
        } else {
            Log::info('ProcessWithdrawRequestJob: 需要人工审核，跳过自动审核', [
                'withdraw_id' => $this->withdraw->id,
                'request_no' => $this->withdraw->request_no,
                'amount' => $this->withdraw->amount,
            ]);
        }
    }
}
