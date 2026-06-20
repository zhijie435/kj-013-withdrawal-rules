<?php

namespace App\Console\Commands;

use App\Enums\PaymentType;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyWithdrawReconcileCommand extends Command
{
    protected $signature = 'withdraw:daily-reconcile';

    protected $description = '每日提现对账统计';

    public function handle(): int
    {
        $this->info('开始执行每日提现对账...');

        $yesterday = now()->subDay();
        $startOfDay = $yesterday->startOfDay();
        $endOfDay = $yesterday->endOfDay();

        $stats = $this->getDailyStats($startOfDay, $endOfDay);

        $this->table(
            ['指标', '笔数', '金额(元)'],
            [
                ['提现申请', $stats['total_count'], number_format($stats['total_amount'], 2)],
                ['提现成功', $stats['success_count'], number_format($stats['success_amount'], 2)],
                ['提现失败', $stats['fail_count'], number_format($stats['fail_amount'], 2)],
                ['待审核', $stats['pending_count'], number_format($stats['pending_amount'], 2)],
                ['手续费', '-', number_format($stats['fee_amount'], 2)],
            ]
        );

        $byMethod = $this->getByMethodStats($startOfDay, $endOfDay);
        if ($byMethod->isNotEmpty()) {
            $this->newLine();
            $this->info('按提现方式统计:');
            $this->table(
                ['方式', '笔数', '金额(元)'],
                $byMethod->map(fn ($item) => [
                    $this->getMethodLabel($item->method),
                    $item->count,
                    number_format($item->amount, 2),
                ])->toArray()
            );
        }

        Log::info('每日提现对账', [
            'date' => $yesterday->toDateString(),
            'stats' => $stats,
        ]);

        $this->newLine();
        $this->info('对账完成！');

        return self::SUCCESS;
    }

    protected function getDailyStats($startDate, $endDate): array
    {
        $query = Payment::where('type', PaymentType::WITHDRAW->value)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalCount = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('amount');

        $successQuery = (clone $query)->where('status', 'completed');
        $successCount = $successQuery->count();
        $successAmount = (float) $successQuery->sum('amount');

        $failQuery = (clone $query)->where('status', 'failed');
        $failCount = $failQuery->count();
        $failAmount = (float) $failQuery->sum('amount');

        $pendingQuery = (clone $query)->where('status', 'pending');
        $pendingCount = $pendingQuery->count();
        $pendingAmount = (float) $pendingQuery->sum('amount');

        $feeAmount = (float) Payment::where('type', PaymentType::WITHDRAW_FEE->value)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        return compact(
            'totalCount', 'totalAmount',
            'successCount', 'successAmount',
            'failCount', 'failAmount',
            'pendingCount', 'pendingAmount',
            'feeAmount'
        );
    }

    protected function getByMethodStats($startDate, $endDate)
    {
        return Payment::select('method', DB::raw('count(*) as count'), DB::raw('sum(amount) as amount'))
            ->where('type', PaymentType::WITHDRAW->value)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('method')
            ->orderBy('amount', 'desc')
            ->get();
    }

    protected function getMethodLabel($method): string
    {
        $labels = [
            'bank_transfer' => '银行转账',
            'alipay' => '支付宝',
            'wechat' => '微信支付',
            'cash' => '现金',
        ];

        return $labels[$method] ?? $method;
    }
}
