<?php

namespace App\Jobs;

use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWithdrawalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $withdrawal;
    public $operatorId;
    public $processingNote;

    public function __construct(Withdrawal $withdrawal, ?int $operatorId = null, string $processingNote = '')
    {
        $this->withdrawal = $withdrawal;
        $this->operatorId = $operatorId;
        $this->processingNote = $processingNote;
    }

    public function handle(WithdrawalService $service): void
    {
        try {
            Log::info("开始处理提现: {$this->withdrawal->withdrawal_no}");

            $service->process($this->withdrawal, [
                'processing_note' => $this->processingNote ?: '队列自动处理',
            ], $this->operatorId);

            sleep(2);

            $service->complete($this->withdrawal, [
                'transaction_id' => 'TXN' . time() . rand(1000, 9999),
                'third_party_no' => 'THIRD' . time() . rand(1000, 9999),
            ], $this->operatorId);

            Log::info("提现处理完成: {$this->withdrawal->withdrawal_no}");
        } catch (\Exception $e) {
            Log::error("提现处理失败: {$this->withdrawal->withdrawal_no}, 错误: " . $e->getMessage());

            try {
                $service->fail($this->withdrawal, $e->getMessage(), $this->operatorId);
            } catch (\Exception $ex) {
                Log::error("更新提现状态失败: {$this->withdrawal->withdrawal_no}, 错误: " . $ex->getMessage());
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("处理提现队列任务失败: {$this->withdrawal->withdrawal_no}, 错误: " . $exception->getMessage());
    }
}
