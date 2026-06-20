<?php

namespace App\Services;

use App\Enums\WithdrawAuditAction;
use App\Enums\WithdrawStatus;
use App\Exceptions\BusinessException;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserWithdrawAccount;
use App\Models\WithdrawAudit;
use App\Models\WithdrawRequest;
use App\Models\WithdrawRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class WithdrawRequestService
{
    public function __construct(
        protected WithdrawRuleService $ruleService,
        protected WalletService $walletService
    ) {}

    public function getRequests(array $params = [], ?User $user = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = WithdrawRequest::query()->with(['user', 'distributor', 'method', 'rule', 'auditor']);

        if ($user && !$user->isPlatform()) {
            $query->where('user_id', $user->id);
        }

        if (isset($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        if (isset($params['distributor_id'])) {
            $query->where('distributor_id', $params['distributor_id']);
        }

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['withdraw_method_id'])) {
            $query->where('withdraw_method_id', $params['withdraw_method_id']);
        }

        if (isset($params['request_no'])) {
            $query->where('request_no', 'like', "%{$params['request_no']}%");
        }

        if (isset($params['start_date'])) {
            $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (isset($params['end_date'])) {
            $query->whereDate('created_at', '<=', $params['end_date']);
        }

        return $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);
    }

    public function getRequest(int $id, ?User $user = null): WithdrawRequest
    {
        $query = WithdrawRequest::with(['user', 'distributor', 'method', 'rule', 'auditor', 'auditRecords.auditor']);

        if ($user && !$user->isPlatform()) {
            $query->where('user_id', $user->id);
        }

        return $query->findOrFail($id);
    }

    public function createRequest(array $data, User $user): WithdrawRequest
    {
        if (!\App\Models\WithdrawRule::isGloballyEnabled()) {
            throw BusinessException::withCode('提现功能已临时关闭，请稍后再试', 'WITHDRAW_GLOBALLY_DISABLED');
        }

        $amount = (float) $data['amount'];
        $methodId = (int) $data['withdraw_method_id'];
        $accountId = $data['account_id'] ?? null;
        $distributorId = $data['distributor_id'] ?? ($user->distributor_id ?? null);

        if (!$distributorId) {
            throw BusinessException::withCode('请指定提现的分销商', 'DISTRIBUTOR_REQUIRED');
        }

        $distributor = Distributor::findOrFail($distributorId);

        $validation = $this->ruleService->validateWithdrawAmount($user, $methodId, $amount);
        $rule = $validation['rule'];
        $fee = $validation['fee'];
        $actualAmount = $validation['actual_amount'];

        $balance = $this->walletService->getDistributorBalance($distributor);
        $minKeep = (float) ($data['min_balance_keep'] ?? 0);

        if (($balance['available_balance'] - $minKeep) < $amount) {
            throw BusinessException::withCode(
                '可提现余额不足',
                'INSUFFICIENT_AVAILABLE_BALANCE',
                [
                    'available' => $balance['available_balance'],
                    'required' => $amount,
                    'min_keep' => $minKeep,
                ]
            );
        }

        $account = null;
        if ($accountId) {
            $account = UserWithdrawAccount::where('user_id', $user->id)
                ->where('id', $accountId)
                ->first();

            if (!$account) {
                throw BusinessException::withCode('提现账户不存在', 'WITHDRAW_ACCOUNT_NOT_FOUND');
            }

            if (!$account->isEnabled()) {
                throw BusinessException::withCode('提现账户已禁用', 'WITHDRAW_ACCOUNT_DISABLED');
            }
        }

        return DB::transaction(function () use (
            $user,
            $distributor,
            $amount,
            $fee,
            $actualAmount,
            $methodId,
            $rule,
            $account,
            $data
        ) {
            $status = $rule->requires_audit ? WithdrawStatus::PENDING : WithdrawStatus::APPROVED;

            $accountInfo = $account ? [
                'account_id' => $account->id,
                'account_name' => $account->account_name,
                'account_number' => $account->account_number,
                'bank_name' => $account->bank_name,
                'bank_branch' => $account->bank_branch,
                'swift_code' => $account->swift_code,
            ] : [
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'swift_code' => $data['swift_code'] ?? null,
                'alipay_account' => $data['alipay_account'] ?? null,
                'wechat_account' => $data['wechat_account'] ?? null,
            ];

            $withdraw = WithdrawRequest::create([
                'user_id' => $user->id,
                'distributor_id' => $distributor->id,
                'withdraw_method_id' => $methodId,
                'withdraw_rule_id' => $rule->id,
                'amount' => $amount,
                'fee' => $fee,
                'actual_amount' => $actualAmount,
                'currency' => $data['currency'] ?? 'CNY',
                'account_info' => $accountInfo,
                'remark' => $data['remark'] ?? null,
                'status' => $status,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::SUBMIT,
                null,
                $status,
                '提交提现申请',
                $user->id
            );

            if (!$rule->requires_audit) {
                $this->processAutoApproval($withdraw, $user);
            }

            return $withdraw->refresh();
        });
    }

    protected function processAutoApproval(WithdrawRequest $withdraw, User $user): void
    {
        $this->createAuditRecord(
            $withdraw,
            WithdrawAuditAction::APPROVE,
            WithdrawStatus::PENDING,
            WithdrawStatus::APPROVED,
            '系统自动审核通过',
            $user->id
        );
    }

    public function approve(WithdrawRequest $withdraw, User $auditor, string $remark = ''): WithdrawRequest
    {
        if (!$withdraw->canAudit()) {
            throw BusinessException::withCode('该提现申请状态不允许审核', 'WITHDRAW_NOT_PENDING');
        }

        return DB::transaction(function () use ($withdraw, $auditor, $remark) {
            $withdraw->status = WithdrawStatus::APPROVED;
            $withdraw->auditor_id = $auditor->id;
            $withdraw->audit_time = now();
            $withdraw->audit_remark = $remark;
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::APPROVE,
                WithdrawStatus::PENDING,
                WithdrawStatus::APPROVED,
                $remark,
                $auditor->id
            );

            return $withdraw->refresh();
        });
    }

    public function reject(WithdrawRequest $withdraw, User $auditor, string $reason): WithdrawRequest
    {
        if (!$withdraw->canAudit()) {
            throw BusinessException::withCode('该提现申请状态不允许审核', 'WITHDRAW_NOT_PENDING');
        }

        return DB::transaction(function () use ($withdraw, $auditor, $reason) {
            $withdraw->status = WithdrawStatus::REJECTED;
            $withdraw->auditor_id = $auditor->id;
            $withdraw->audit_time = now();
            $withdraw->audit_remark = $reason;
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::REJECT,
                WithdrawStatus::PENDING,
                WithdrawStatus::REJECTED,
                $reason,
                $auditor->id
            );

            return $withdraw->refresh();
        });
    }

    public function cancel(WithdrawRequest $withdraw, User $user): WithdrawRequest
    {
        if (!$withdraw->canCancel()) {
            throw BusinessException::withCode('该提现申请状态不允许取消', 'WITHDRAW_NOT_CANCELLABLE');
        }

        if ($withdraw->user_id !== $user->id && !$user->isPlatform()) {
            throw BusinessException::withCode('无权取消该提现申请', 'PERMISSION_DENIED');
        }

        return DB::transaction(function () use ($withdraw, $user) {
            $withdraw->status = WithdrawStatus::CANCELLED;
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::CANCEL,
                WithdrawStatus::PENDING,
                WithdrawStatus::CANCELLED,
                '用户取消申请',
                $user->id
            );

            return $withdraw->refresh();
        });
    }

    public function process(WithdrawRequest $withdraw, User $operator, array $data = []): WithdrawRequest
    {
        if (!$withdraw->canTransitionTo(WithdrawStatus::PROCESSING)) {
            throw BusinessException::withCode('该提现申请状态不允许开始处理', 'WITHDRAW_INVALID_STATUS');
        }

        return DB::transaction(function () use ($withdraw, $operator, $data) {
            $distributor = $withdraw->distributor;
            if ($distributor) {
                $this->walletService->deductBalance(
                    $distributor,
                    (float) $withdraw->amount + (float) $withdraw->fee,
                    "提现扣款 #{$withdraw->request_no}",
                    $operator->id
                );
            }

            $withdraw->status = WithdrawStatus::PROCESSING;
            $withdraw->processed_at = now();
            if (isset($data['transaction_no'])) {
                $withdraw->transaction_no = $data['transaction_no'];
            }
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::PROCESS,
                WithdrawStatus::APPROVED,
                WithdrawStatus::PROCESSING,
                $data['remark'] ?? '开始打款处理',
                $operator->id
            );

            return $withdraw->refresh();
        });
    }

    public function complete(WithdrawRequest $withdraw, User $operator, array $data = []): WithdrawRequest
    {
        if (!$withdraw->canTransitionTo(WithdrawStatus::COMPLETED)) {
            throw BusinessException::withCode('该提现申请状态不允许完成', 'WITHDRAW_INVALID_STATUS');
        }

        return DB::transaction(function () use ($withdraw, $operator, $data) {
            $withdraw->status = WithdrawStatus::COMPLETED;
            if (isset($data['transaction_no'])) {
                $withdraw->transaction_no = $data['transaction_no'];
            }
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::COMPLETE,
                WithdrawStatus::PROCESSING,
                WithdrawStatus::COMPLETED,
                $data['remark'] ?? '打款完成',
                $operator->id
            );

            return $withdraw->refresh();
        });
    }

    public function fail(WithdrawRequest $withdraw, User $operator, string $reason): WithdrawRequest
    {
        if (!$withdraw->canTransitionTo(WithdrawStatus::FAILED)) {
            throw BusinessException::withCode('该提现申请状态不允许标记为失败', 'WITHDRAW_INVALID_STATUS');
        }

        return DB::transaction(function () use ($withdraw, $operator, $reason) {
            $distributor = $withdraw->distributor;
            if ($distributor && $withdraw->status === WithdrawStatus::PROCESSING) {
                $this->walletService->addBalance(
                    $distributor,
                    (float) $withdraw->amount + (float) $withdraw->fee,
                    "提现失败退回 #{$withdraw->request_no}",
                    $operator->id
                );
            }

            $withdraw->status = WithdrawStatus::FAILED;
            $withdraw->failure_reason = $reason;
            $withdraw->save();

            $this->createAuditRecord(
                $withdraw,
                WithdrawAuditAction::FAIL,
                $withdraw->status,
                WithdrawStatus::FAILED,
                $reason,
                $operator->id
            );

            return $withdraw->refresh();
        });
    }

    public function batchApprove(array $ids, User $auditor, string $remark = ''): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($ids as $id) {
            try {
                $withdraw = WithdrawRequest::find($id);
                if (!$withdraw) {
                    $results['failed']++;
                    $results['errors'][] = "ID {$id}: 记录不存在";
                    continue;
                }

                $this->approve($withdraw, $auditor, $remark);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "ID {$id}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    public function batchReject(array $ids, User $auditor, string $reason): array
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($ids as $id) {
            try {
                $withdraw = WithdrawRequest::find($id);
                if (!$withdraw) {
                    $results['failed']++;
                    $results['errors'][] = "ID {$id}: 记录不存在";
                    continue;
                }

                $this->reject($withdraw, $auditor, $reason);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "ID {$id}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    protected function createAuditRecord(
        WithdrawRequest $withdraw,
        WithdrawAuditAction $action,
        ?WithdrawStatus $fromStatus,
        ?WithdrawStatus $toStatus,
        string $remark = '',
        ?int $auditorId = null
    ): WithdrawAudit {
        return WithdrawAudit::create([
            'withdraw_request_id' => $withdraw->id,
            'auditor_id' => $auditorId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'remark' => $remark,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public function getStatistics(array $params = []): array
    {
        $query = WithdrawRequest::query();

        if (isset($params['start_date'])) {
            $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (isset($params['end_date'])) {
            $query->whereDate('created_at', '<=', $params['end_date']);
        }

        $totalCount = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('amount');
        $totalFee = (float) (clone $query)->sum('fee');

        $pendingCount = (clone $query)->where('status', WithdrawStatus::PENDING)->count();
        $pendingAmount = (float) (clone $query)->where('status', WithdrawStatus::PENDING)->sum('amount');

        $approvedCount = (clone $query)->where('status', WithdrawStatus::APPROVED)->count();
        $approvedAmount = (float) (clone $query)->where('status', WithdrawStatus::APPROVED)->sum('amount');

        $processingCount = (clone $query)->where('status', WithdrawStatus::PROCESSING)->count();
        $processingAmount = (float) (clone $query)->where('status', WithdrawStatus::PROCESSING)->sum('amount');

        $completedCount = (clone $query)->where('status', WithdrawStatus::COMPLETED)->count();
        $completedAmount = (float) (clone $query)->where('status', WithdrawStatus::COMPLETED)->sum('amount');

        $rejectedCount = (clone $query)->where('status', WithdrawStatus::REJECTED)->count();
        $rejectedAmount = (float) (clone $query)->where('status', WithdrawStatus::REJECTED)->sum('amount');

        $failedCount = (clone $query)->where('status', WithdrawStatus::FAILED)->count();
        $failedAmount = (float) (clone $query)->where('status', WithdrawStatus::FAILED)->sum('amount');

        $cancelledCount = (clone $query)->where('status', WithdrawStatus::CANCELLED)->count();
        $cancelledAmount = (float) (clone $query)->where('status', WithdrawStatus::CANCELLED)->sum('amount');

        return [
            'total' => [
                'count' => $totalCount,
                'amount' => $totalAmount,
                'fee' => $totalFee,
            ],
            'pending' => ['count' => $pendingCount, 'amount' => $pendingAmount],
            'approved' => ['count' => $approvedCount, 'amount' => $approvedAmount],
            'processing' => ['count' => $processingCount, 'amount' => $processingAmount],
            'completed' => ['count' => $completedCount, 'amount' => $completedAmount],
            'rejected' => ['count' => $rejectedCount, 'amount' => $rejectedAmount],
            'failed' => ['count' => $failedCount, 'amount' => $failedAmount],
            'cancelled' => ['count' => $cancelledCount, 'amount' => $cancelledAmount],
        ];
    }

    public function getUserStatistics(User $user, array $params = []): array
    {
        $params['user_id'] = $user->id;
        return $this->getStatistics($params);
    }

    public function getPendingCount(): int
    {
        return WithdrawRequest::pending()->count();
    }
}
