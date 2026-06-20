<?php

namespace App\Jobs;

use App\Enums\WithdrawStatus;
use App\Models\User;
use App\Models\WithdrawRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchProcessWithdrawalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        protected int $batchSize = 50
    ) {}

    public function handle(): void
    {
        try {
            $operator = User::where('user_type', 'platform')->first();

            if (!$operator) {
                Log::error('BatchProcessWithdrawalsJob: 未找到平台操作员');
                return;
            }

            $withdrawals = WithdrawRequest::where('status', WithdrawStatus::APPROVED)
                ->orderBy('id', 'asc')
                ->limit($this->batchSize)
                ->get();

            if ($withdrawals->isEmpty()) {
                Log::info('BatchProcessWithdrawalsJob: 没有需要处理的提现申请');
                return;
            }

            Log::info('BatchProcessWithdrawalsJob: 开始批量处理提现', [
                'count' => $withdrawals->count(),
            ]);

            foreach ($withdrawals as $withdraw) {
                ProcessWithdrawRequestJob::dispatch($withdraw, 'process', $operator)
                    ->onQueue('withdrawals');
            }

            Log::info('BatchProcessWithdrawalsJob: 批量处理任务已分发', [
                'count' => $withdrawals->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('BatchProcessWithdrawalsJob: 批量处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        }
    }
}
