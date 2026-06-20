<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWithdrawRequestJob;
use App\Models\WithdrawRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoAuditWithdrawals extends Command
{
    protected $signature = 'withdraw:auto-audit {--limit=50}';

    protected $description = '自动审核符合条件的提现申请';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        try {
            $withdrawals = WithdrawRequest::pending()
                ->whereHas('rule', function ($query) {
                    $query->where('requires_audit', false);
                })
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();

            if ($withdrawals->isEmpty()) {
                $this->info('没有需要自动审核的提现申请');
                Log::info('AutoAuditWithdrawals: 没有需要自动审核的提现申请');
                return 0;
            }

            $this->info("找到 {$withdrawals->count()} 条需要自动审核的提现申请");
            Log::info('AutoAuditWithdrawals: 开始自动审核', ['count' => $withdrawals->count()]);

            foreach ($withdrawals as $withdraw) {
                ProcessWithdrawRequestJob::dispatch($withdraw, 'auto_audit')
                    ->onQueue('withdrawals');

                $this->line("已分发审核任务: {$withdraw->request_no}");
            }

            $this->info("成功分发 {$withdrawals->count()} 条自动审核任务");
            Log::info('AutoAuditWithdrawals: 自动审核任务分发完成', ['count' => $withdrawals->count()]);

            return 0;
        } catch (\Exception $e) {
            $this->error('自动审核失败: ' . $e->getMessage());
            Log::error('AutoAuditWithdrawals: 自动审核失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
