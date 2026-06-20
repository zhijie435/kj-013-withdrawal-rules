<?php

namespace App\Console\Commands;

use App\Jobs\BatchProcessWithdrawalsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BatchProcessWithdrawals extends Command
{
    protected $signature = 'withdraw:batch-process {--batch-size=50}';

    protected $description = '批量处理已审核通过的提现申请';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');

        try {
            $this->info("开始批量处理提现申请，批量大小: {$batchSize}");
            Log::info('BatchProcessWithdrawals: 开始批量处理', ['batch_size' => $batchSize]);

            BatchProcessWithdrawalsJob::dispatch($batchSize)
                ->onQueue('withdrawals');

            $this->info('批量处理任务已分发到队列');
            Log::info('BatchProcessWithdrawals: 任务已分发');

            return 0;
        } catch (\Exception $e) {
            $this->error('批量处理失败: ' . $e->getMessage());
            Log::error('BatchProcessWithdrawals: 处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
