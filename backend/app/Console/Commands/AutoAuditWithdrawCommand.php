<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\ProcessWithdrawJob;
use App\Models\Payment;
use App\Models\ShearerlineConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoAuditWithdrawCommand extends Command
{
    protected $signature = 'withdraw:auto-audit';

    protected $description = '自动审核小额提现申请';

    public function handle(): int
    {
        $this->info('开始执行自动审核提现...');

        $requireAudit = ShearerlineConfig::getWithdrawRule('require_audit');
        $auditThreshold = ShearerlineConfig::getWithdrawRule('audit_threshold');

        if (!$requireAudit) {
            $this->info('提现无需审核，跳过自动审核');
            return self::SUCCESS;
        }

        $pendingWithdraws = Payment::pendingWithdraws()
            ->where('amount', '<', $auditThreshold)
            ->where('created_at', '<=', now()->subMinutes(10))
            ->get();

        if ($pendingWithdraws->isEmpty()) {
            $this->info('没有需要自动审核的提现申请');
            return self::SUCCESS;
        }

        $this->info("找到 {$pendingWithdraws->count()} 笔待自动审核的提现申请");

        $successCount = 0;
        $failCount = 0;

        foreach ($pendingWithdraws as $payment) {
            try {
                ProcessWithdrawJob::dispatch($payment);
                $successCount++;
                $this->line("  ✅ 已加入审核队列: {$payment->payment_no} (¥{$payment->amount})");
            } catch (\Exception $e) {
                $failCount++;
                $this->line("  ❌ 审核失败: {$payment->payment_no} - {$e->getMessage()}");
                Log::error('自动审核提现失败', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("自动审核完成：成功 {$successCount} 笔，失败 {$failCount} 笔");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }
}
