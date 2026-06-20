<?php

namespace App\Console\Commands;

use App\Enums\WithdrawStatus;
use App\Models\WithdrawRequest;
use App\Services\WithdrawRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WithdrawDailyReconcile extends Command
{
    protected $signature = 'withdraw:daily-reconcile {--date=}';

    protected $description = '提现每日对账统计';

    public function handle(WithdrawRequestService $service): int
    {
        try {
            $date = $this->option('date') ?? now()->subDay()->toDateString();

            $this->info("开始执行 {$date} 的提现对账");
            Log::info('WithdrawDailyReconcile: 开始对账', ['date' => $date]);

            $params = [
                'start_date' => $date,
                'end_date' => $date,
            ];

            $statistics = $service->getStatistics($params);

            $this->table(
                ['统计项', '笔数', '金额(元)'],
                [
                    ['总计', $statistics['total']['count'], number_format($statistics['total']['amount'], 2)],
                    ['待审核', $statistics['pending']['count'], number_format($statistics['pending']['amount'], 2)],
                    ['已通过', $statistics['approved']['count'], number_format($statistics['approved']['amount'], 2)],
                    ['处理中', $statistics['processing']['count'], number_format($statistics['processing']['amount'], 2)],
                    ['已完成', $statistics['completed']['count'], number_format($statistics['completed']['amount'], 2)],
                    ['已驳回', $statistics['rejected']['count'], number_format($statistics['rejected']['amount'], 2)],
                    ['失败', $statistics['failed']['count'], number_format($statistics['failed']['amount'], 2)],
                    ['已取消', $statistics['cancelled']['count'], number_format($statistics['cancelled']['amount'], 2)],
                ]
            );

            $totalFee = $statistics['total']['fee'];
            $this->info("当日手续费总计: ¥" . number_format($totalFee, 2));

            $pendingCount = WithdrawRequest::pending()->count();
            if ($pendingCount > 0) {
                $this->warn("当前待审核提现申请: {$pendingCount} 条，请及时处理");
            }

            Log::info('WithdrawDailyReconcile: 对账完成', [
                'date' => $date,
                'statistics' => $statistics,
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->error('对账失败: ' . $e->getMessage());
            Log::error('WithdrawDailyReconcile: 对账失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }
}
