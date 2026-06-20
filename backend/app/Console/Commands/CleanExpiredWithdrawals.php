<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanExpiredWithdrawals extends Command
{
    protected $signature = 'withdrawal:clean-expired';

    protected $description = '清理已取消/失败超过30天的提现记录';

    public function handle(): int
    {
        $this->info('开始执行清理过期提现记录任务...');
        Log::info('清理过期提现记录任务开始执行');

        try {
            $expiredDate = now()->subDays(30);

            $count = Withdrawal::onlyTrashed()
                ->whereIn('status', [Withdrawal::STATUS_CANCELLED, Withdrawal::STATUS_REJECTED, Withdrawal::STATUS_FAILED])
                ->where('deleted_at', '<=', $expiredDate)
                ->forceDelete();

            $this->info("清理了 {$count} 条过期提现记录");
            Log::info("清理了 {$count} 条过期提现记录");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('清理过期提现记录任务执行失败: ' . $e->getMessage());
            Log::error('清理过期提现记录任务执行失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
