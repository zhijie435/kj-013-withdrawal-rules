<?php

namespace App\Services;

use App\Enums\UserType;
use App\Exceptions\Withdrawal\DailyLimitExceededException;
use App\Exceptions\Withdrawal\InsufficientBalanceException;
use App\Exceptions\Withdrawal\InvalidStatusTransitionException;
use App\Exceptions\Withdrawal\InvalidWithdrawalAmountException;
use App\Exceptions\Withdrawal\WalletNotFoundException;
use App\Exceptions\Withdrawal\WithdrawalRuleNotFoundException;
use App\Models\BankCard;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\WithdrawalRule;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class WithdrawalService
{
    protected string $redisKeyPrefix = 'withdrawal:';

    public function generateWithdrawalNo(): string
    {
        return Withdrawal::generateNo();
    }

    public function getApplicableRule(
        string $userLevel,
        string $currency,
        string $method
    ): ?WithdrawalRule {
        return WithdrawalRule::where('is_active', true)
            ->where(function ($query) use ($userLevel) {
                $query->where('user_level', WithdrawalRule::LEVEL_ALL)
                    ->orWhere('user_level', $userLevel);
            })
            ->where('currency', $currency)
            ->where('withdrawal_method', $method)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('fee_rate', 'asc')
            ->first();
    }

    public function calculateFee(float $amount, WithdrawalRule $rule): float
    {
        return $rule->calculateFee($amount);
    }

    public function validateWithdrawalRequest(User $user, array $data): array
    {
        $currency = $data['currency'] ?? 'CNY';
        $method = $data['withdrawal_method'] ?? 'bank_transfer';
        $amount = (float) $data['request_amount'];

        $rule = $this->getApplicableRule($user->level, $currency, $method);
        if (!$rule) {
            throw new WithdrawalRuleNotFoundException(
                "未找到适用于用户等级[{$user->level}]、币种[{$currency}]、方式[{$method}]的提现规则"
            );
        }

        if ($amount < $rule->min_amount) {
            throw new InvalidWithdrawalAmountException(
                "提现金额不能低于最低限额: {$rule->min_amount} {$currency}"
            );
        }

        if ($amount > $rule->max_amount) {
            throw new InvalidWithdrawalAmountException(
                "提现金额不能超过最高限额: {$rule->max_amount} {$currency}"
            );
        }

        $wallet = $user->wallet($currency)->first();
        if (!$wallet) {
            throw new WalletNotFoundException("用户 {$currency} 钱包不存在");
        }

        if (!$wallet->canWithdraw($amount)) {
            throw new InsufficientBalanceException('可用余额不足');
        }

        $todayWithdrawn = $this->getTodayWithdrawnAmount($user->id, $currency);
        if (($todayWithdrawn + $amount) > $rule->daily_limit) {
            throw new DailyLimitExceededException(
                "今日已提现 {$todayWithdrawn} {$currency}，今日剩余可提现额度: " .
                ($rule->daily_limit - $todayWithdrawn) . " {$currency}"
            );
        }

        $todayCount = $this->getTodayWithdrawalCount($user->id, $currency);
        if ($todayCount >= $rule->daily_max_count) {
            throw new DailyLimitExceededException(
                "今日提现次数已达上限: {$rule->daily_max_count} 次"
            );
        }

        $fee = $this->calculateFee($amount, $rule);
        $actualAmount = $amount - $fee;

        if ($actualAmount <= 0) {
            throw new InvalidWithdrawalAmountException('实际到账金额必须大于0');
        }

        return [
            'rule' => $rule,
            'wallet' => $wallet,
            'fee' => $fee,
            'actual_amount' => $actualAmount,
        ];
    }

    public function applyWithdrawal(User $user, array $data): Withdrawal
    {
        return DB::transaction(function () use ($user, $data) {
            $validated = $this->validateWithdrawalRequest($user, $data);
            $rule = $validated['rule'];
            $wallet = $validated['wallet'];
            $fee = $validated['fee'];
            $actualAmount = $validated['actual_amount'];
            $amount = (float) $data['request_amount'];

            $bankCard = BankCard::findOrFail($data['bank_card_id']);
            if ($bankCard->user_id !== $user->id) {
                throw new \App\Exceptions\Withdrawal\BankCardNotFoundException('银行卡不存在');
            }
            if (!$bankCard->is_active) {
                throw new \App\Exceptions\Withdrawal\WithdrawalException('银行卡已禁用');
            }

            $needApproval = $rule->require_approval || $amount >= $rule->approval_threshold;
            $status = $needApproval ? Withdrawal::STATUS_PENDING : Withdrawal::STATUS_APPROVED;

            $withdrawal = Withdrawal::create([
                'withdrawal_no' => $this->generateWithdrawalNo(),
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'rule_id' => $rule->id,
                'bank_card_id' => $bankCard->id,
                'currency' => $rule->currency,
                'withdrawal_method' => $rule->withdrawal_method,
                'request_amount' => $amount,
                'fee_rate' => $rule->fee_rate,
                'fee_amount' => $fee,
                'actual_amount' => $actualAmount,
                'status' => $status,
                'remark' => $data['remark'] ?? null,
                'created_by' => $user->id,
            ]);

            $withdrawal->addAuditLog('submit', '提交提现申请', $user->id);

            $wallet->freeze($amount);

            $wallet->incrementBalance(
                -$amount,
                WalletTransaction::TYPE_WITHDRAWAL,
                [
                    'related_type' => Withdrawal::class,
                    'related_id' => $withdrawal->id,
                    'description' => "提现申请冻结，单号: {$withdrawal->withdrawal_no}",
                ]
            );

            if ($fee > 0) {
                $wallet->incrementBalance(
                    -$fee,
                    WalletTransaction::TYPE_WITHDRAWAL_FEE,
                    [
                        'related_type' => Withdrawal::class,
                        'related_id' => $withdrawal->id,
                        'description' => "提现手续费，单号: {$withdrawal->withdrawal_no}",
                    ]
                );
            }

            if (!$needApproval) {
                $withdrawal->update([
                    'approved_at' => now(),
                    'approved_by' => $user->id,
                ]);
                $withdrawal->addAuditLog('auto_approve', '自动审批通过', $user->id);
            }

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard']);
        });
    }

    public function approve(Withdrawal $withdrawal, ?string $remark = '', ?int $operatorId = null): Withdrawal
    {
        if (!$withdrawal->canApprove()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_APPROVED);
        }

        return DB::transaction(function () use ($withdrawal, $remark, $operatorId) {
            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $operatorId ?? auth()->id(),
            ]);

            $withdrawal->addAuditLog('approve', $remark, $operatorId);

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard', 'approver']);
        });
    }

    public function reject(Withdrawal $withdrawal, string $reason, ?int $operatorId = null): Withdrawal
    {
        if (!$withdrawal->canReject()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_REJECTED);
        }

        return DB::transaction(function () use ($withdrawal, $reason, $operatorId) {
            $withdrawal->update([
                'status' => Withdrawal::STATUS_REJECTED,
                'reject_reason' => $reason,
            ]);

            $withdrawal->addAuditLog('reject', $reason, $operatorId);

            $this->refundToWallet($withdrawal, '提现申请被拒绝');

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard']);
        });
    }

    public function process(Withdrawal $withdrawal, array $data = [], ?int $operatorId = null): Withdrawal
    {
        if (!$withdrawal->canProcess()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_PROCESSING);
        }

        return DB::transaction(function () use ($withdrawal, $data, $operatorId) {
            $withdrawal->update(array_merge([
                'status' => Withdrawal::STATUS_PROCESSING,
                'processed_at' => now(),
                'processed_by' => $operatorId ?? auth()->id(),
                'processing_note' => $data['processing_note'] ?? null,
            ], $data));

            $withdrawal->addAuditLog('process', $data['processing_note'] ?? '开始打款处理', $operatorId);

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard', 'processor']);
        });
    }

    public function complete(Withdrawal $withdrawal, array $data = [], ?int $operatorId = null): Withdrawal
    {
        if (!$withdrawal->canComplete()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_COMPLETED);
        }

        return DB::transaction(function () use ($withdrawal, $data, $operatorId) {
            $withdrawal->update(array_merge([
                'status' => Withdrawal::STATUS_COMPLETED,
                'completed_at' => now(),
            ], $data));

            $withdrawal->addAuditLog('complete', $data['remark'] ?? '打款完成', $operatorId);

            $wallet = $withdrawal->wallet;
            $wallet->unfreeze($withdrawal->request_amount);
            $wallet->increment('total_withdrawn', $withdrawal->actual_amount);

            $this->updateTodayWithdrawn($withdrawal);

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard']);
        });
    }

    public function fail(Withdrawal $withdrawal, string $reason, ?int $operatorId = null): Withdrawal
    {
        if (!$withdrawal->canFail()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_FAILED);
        }

        return DB::transaction(function () use ($withdrawal, $reason, $operatorId) {
            $withdrawal->update([
                'status' => Withdrawal::STATUS_FAILED,
                'fail_reason' => $reason,
            ]);

            $withdrawal->addAuditLog('fail', $reason, $operatorId);

            $this->refundToWallet($withdrawal, '打款失败，资金退回');

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard']);
        });
    }

    public function cancel(Withdrawal $withdrawal, string $reason = ''): Withdrawal
    {
        if (!$withdrawal->canCancel()) {
            throw InvalidStatusTransitionException::for($withdrawal->status, Withdrawal::STATUS_CANCELLED);
        }

        return DB::transaction(function () use ($withdrawal, $reason) {
            $withdrawal->update([
                'status' => Withdrawal::STATUS_CANCELLED,
                'cancel_reason' => $reason,
            ]);

            $withdrawal->addAuditLog('cancel', $reason ?: '用户取消申请');

            $this->refundToWallet($withdrawal, '用户取消提现');

            return $withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard']);
        });
    }

    protected function refundToWallet(Withdrawal $withdrawal, string $description): void
    {
        $wallet = $withdrawal->wallet;
        $amount = $withdrawal->request_amount;
        $fee = $withdrawal->fee_amount;

        if ($withdrawal->status === Withdrawal::STATUS_PROCESSING) {
            $wallet->unfreeze($amount);
        }

        $wallet->incrementBalance(
            $amount,
            WalletTransaction::TYPE_WITHDRAWAL_REFUND,
            [
                'related_type' => Withdrawal::class,
                'related_id' => $withdrawal->id,
                'description' => "{$description}，单号: {$withdrawal->withdrawal_no}",
            ]
        );

        if ($fee > 0) {
            $wallet->incrementBalance(
                $fee,
                WalletTransaction::TYPE_WITHDRAWAL_REFUND,
                [
                    'related_type' => Withdrawal::class,
                    'related_id' => $withdrawal->id,
                    'description' => "退还手续费，单号: {$withdrawal->withdrawal_no}",
                ]
            );
        }
    }

    public function getTodayWithdrawnAmount(int $userId, string $currency = 'CNY'): float
    {
        $redisKey = "{$this->redisKeyPrefix}daily_amount:{$userId}:{$currency}:" . now()->toDateString();
        $amount = Redis::get($redisKey);

        if ($amount === null) {
            $amount = Withdrawal::where('user_id', $userId)
                ->where('currency', $currency)
                ->whereDate('created_at', now())
                ->whereIn('status', [
                    Withdrawal::STATUS_APPROVED,
                    Withdrawal::STATUS_PROCESSING,
                    Withdrawal::STATUS_COMPLETED,
                    Withdrawal::STATUS_SETTLED,
                ])
                ->sum('request_amount');

            Redis::setex($redisKey, 86400, $amount);
        }

        return (float) $amount;
    }

    public function getTodayWithdrawalCount(int $userId, string $currency = 'CNY'): int
    {
        $redisKey = "{$this->redisKeyPrefix}daily_count:{$userId}:{$currency}:" . now()->toDateString();
        $count = Redis::get($redisKey);

        if ($count === null) {
            $count = Withdrawal::where('user_id', $userId)
                ->where('currency', $currency)
                ->whereDate('created_at', now())
                ->where('status', '!=', Withdrawal::STATUS_CANCELLED)
                ->count();

            Redis::setex($redisKey, 86400, $count);
        }

        return (int) $count;
    }

    public function updateTodayWithdrawn(Withdrawal $withdrawal): void
    {
        $userId = $withdrawal->user_id;
        $currency = $withdrawal->currency;
        $date = $withdrawal->created_at->toDateString();

        $amountKey = "{$this->redisKeyPrefix}daily_amount:{$userId}:{$currency}:{$date}";
        $countKey = "{$this->redisKeyPrefix}daily_count:{$userId}:{$currency}:{$date}";

        Redis::incrbyfloat($amountKey, $withdrawal->request_amount);
        Redis::incr($countKey);
    }

    public function getWithdrawalList(array $params = [])
    {
        $query = Withdrawal::with(['user', 'wallet', 'rule', 'bankCard', 'approver', 'processor'])
            ->when(!empty($params['user_id']), function ($q) use ($params) {
                $q->where('user_id', $params['user_id']);
            })
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('withdrawal_no', 'like', "%{$params['keyword']}%")
                        ->orWhereHas('user', function ($q) use ($params) {
                            $q->where('name', 'like', "%{$params['keyword']}%")
                                ->orWhere('phone', 'like', "%{$params['keyword']}%")
                                ->orWhere('email', 'like', "%{$params['keyword']}%");
                        });
                });
            })
            ->when(!empty($params['status']), function ($q) use ($params) {
                $q->where('status', $params['status']);
            })
            ->when(!empty($params['currency']), function ($q) use ($params) {
                $q->where('currency', $params['currency']);
            })
            ->when(!empty($params['withdrawal_method']), function ($q) use ($params) {
                $q->where('withdrawal_method', $params['withdrawal_method']);
            })
            ->when(!empty($params['start_date']), function ($q) use ($params) {
                $q->where('created_at', '>=', $params['start_date']);
            })
            ->when(!empty($params['end_date']), function ($q) use ($params) {
                $q->where('created_at', '<=', $params['end_date'] . ' 23:59:59');
            })
            ->when(!empty($params['min_amount']), function ($q) use ($params) {
                $q->where('request_amount', '>=', $params['min_amount']);
            })
            ->when(!empty($params['max_amount']), function ($q) use ($params) {
                $q->where('request_amount', '<=', $params['max_amount']);
            })
            ->orderBy('created_at', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getStatistics(array $params = []): array
    {
        $dateStart = $params['date_start'] ?? now()->startOfMonth()->toDateString();
        $dateEnd = $params['date_end'] ?? now()->endOfMonth()->toDateString();

        $query = Withdrawal::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59']);

        $totalCount = $query->count();
        $totalAmount = (clone $query)->sum('request_amount');
        $totalFee = (clone $query)->sum('fee_amount');
        $totalActual = (clone $query)->sum('actual_amount');

        $statusStats = Withdrawal::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->selectRaw('status, COUNT(*) as count, SUM(request_amount) as amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $pendingAmount = $statusStats[Withdrawal::STATUS_PENDING]->amount ?? 0;
        $approvedAmount = $statusStats[Withdrawal::STATUS_APPROVED]->amount ?? 0;
        $processingAmount = $statusStats[Withdrawal::STATUS_PROCESSING]->amount ?? 0;
        $completedAmount = $statusStats[Withdrawal::STATUS_COMPLETED]->amount ?? 0;
        $rejectedAmount = $statusStats[Withdrawal::STATUS_REJECTED]->amount ?? 0;
        $failedAmount = $statusStats[Withdrawal::STATUS_FAILED]->amount ?? 0;

        $pendingCount = $statusStats[Withdrawal::STATUS_PENDING]->count ?? 0;
        $approvedCount = $statusStats[Withdrawal::STATUS_APPROVED]->count ?? 0;
        $processingCount = $statusStats[Withdrawal::STATUS_PROCESSING]->count ?? 0;
        $completedCount = $statusStats[Withdrawal::STATUS_COMPLETED]->count ?? 0;
        $rejectedCount = $statusStats[Withdrawal::STATUS_REJECTED]->count ?? 0;
        $failedCount = $statusStats[Withdrawal::STATUS_FAILED]->count ?? 0;

        $methodStats = Withdrawal::whereBetween('created_at', [$dateStart, $dateEnd . ' 23:59:59'])
            ->selectRaw('withdrawal_method, COUNT(*) as count, SUM(request_amount) as amount')
            ->groupBy('withdrawal_method')
            ->get()
            ->keyBy('withdrawal_method');

        $userCount = User::whereNotIn('user_type', [UserType::PLATFORM, UserType::SUPPLIER])->count();
        $activeUserCount = User::whereNotIn('user_type', [UserType::PLATFORM, UserType::SUPPLIER])
            ->where('is_active', true)
            ->count();

        return [
            'total' => [
                'count' => $totalCount,
                'request_amount' => round($totalAmount, 2),
                'fee_amount' => round($totalFee, 2),
                'actual_amount' => round($totalActual, 2),
            ],
            'status' => [
                'pending' => ['count' => $pendingCount, 'amount' => round($pendingAmount, 2)],
                'approved' => ['count' => $approvedCount, 'amount' => round($approvedAmount, 2)],
                'processing' => ['count' => $processingCount, 'amount' => round($processingAmount, 2)],
                'completed' => ['count' => $completedCount, 'amount' => round($completedAmount, 2)],
                'rejected' => ['count' => $rejectedCount, 'amount' => round($rejectedAmount, 2)],
                'failed' => ['count' => $failedCount, 'amount' => round($failedAmount, 2)],
            ],
            'methods' => $methodStats,
            'users' => [
                'total' => $userCount,
                'active' => $activeUserCount,
            ],
            'date_range' => [
                'start' => $dateStart,
                'end' => $dateEnd,
            ],
            'success_rate' => $totalCount > 0
                ? round(($completedCount / $totalCount) * 100, 2)
                : 0,
        ];
    }

    public function batchApprove(array $ids, string $remark = '', ?int $operatorId = null): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($ids as $id) {
            try {
                $withdrawal = Withdrawal::findOrFail($id);
                $this->approve($withdrawal, $remark, $operatorId);
                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $id, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function batchProcess(array $ids, array $data = [], ?int $operatorId = null): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($ids as $id) {
            try {
                $withdrawal = Withdrawal::findOrFail($id);
                $this->process($withdrawal, $data, $operatorId);
                $results['success'][] = $id;
            } catch (\Exception $e) {
                $results['failed'][] = ['id' => $id, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function autoSettle(): void
    {
        $settlementDays = config('withdrawal.settlement_days', 7);
        $settleDate = now()->subDays($settlementDays);

        Withdrawal::where('status', Withdrawal::STATUS_COMPLETED)
            ->whereNull('settled_at')
            ->where('completed_at', '<=', $settleDate)
            ->chunk(100, function ($withdrawals) {
                foreach ($withdrawals as $withdrawal) {
                    DB::transaction(function () use ($withdrawal) {
                        $withdrawal->update([
                            'status' => Withdrawal::STATUS_SETTLED,
                            'settled_at' => now(),
                        ]);

                        $withdrawal->addAuditLog('settle', '系统自动结算');
                    });
                }
            });
    }
}
