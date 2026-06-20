<?php

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetDailyWithdrawn extends Command
{
    protected $signature = 'withdrawal:reset-daily';

    protected $description = '重置每日提现金额统计';

    public function handle(WalletService $service): int
    {
        $this->info('开始执行重置每日提现统计任务...');
        Log::info('重置每日提现统计任务开始执行');

        try {
            $service->resetDailyWithdrawn();

            $this->info('重置每日提现统计任务执行完成');
            Log::info('重置每日提现统计任务执行完成');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('重置每日提现统计任务执行失败: ' . $e->getMessage());
            Log::error('重置每日提现统计任务执行失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
