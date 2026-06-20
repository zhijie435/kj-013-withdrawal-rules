<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyPendingWithdrawals extends Command
{
    protected $signature = 'withdrawal:notify-pending';

    protected $description = '通知财务处理待审核的提现订单';

    public function handle(): int
    {
        $this->info('开始执行待审核通知任务...');
        Log::info('待审核通知任务开始执行');

        try {
            $pendingCount = Withdrawal::where('status', Withdrawal::STATUS_PENDING)
                ->where('created_at', '<=', now()->subHour())
                ->count();

            if ($pendingCount > 0) {
                $this->warn("有 {$pendingCount} 条提现申请等待审核超过1小时");
                Log::warning("有 {$pendingCount} 条提现申请等待审核超过1小时");

                $this->sendNotification($pendingCount);
            } else {
                $this->info('没有超时未处理的提现申请');
                Log::info('没有超时未处理的提现申请');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('待审核通知任务执行失败: ' . $e->getMessage());
            Log::error('待审核通知任务执行失败: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function sendNotification(int $count): void
    {
        Log::info("发送通知: 有 {$count} 条提现申请待处理");
    }
}
