<?php

namespace App\Console\Commands;

use App\Services\WithdrawalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoSettleWithdrawals extends Command
{
    protected $signature = 'withdrawal:auto-settle';

    protected $description = '自动结算已完成的提现订单';

    public function handle(WithdrawalService $service): int
    {
        $this->info('开始执行自动结算任务...');
        Log::info('自动结算任务开始执行');

        try {
            $service->autoSettle();

            $this->info('自动结算任务执行完成');
            Log::info('自动结算任务执行完成');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('自动结算任务执行失败: ' . $e->getMessage());
            Log::error('自动结算任务执行失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
