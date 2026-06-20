<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-withdrawals');

        $params = $request->all();

        if (!$request->user()->can('manage-withdrawals') && !$request->user()->can('approve-withdrawal')) {
            $params['user_id'] = $request->user()->id;
        }

        $withdrawals = $this->service->getWithdrawalList(array_merge([
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 15),
        ], $params));

        return $this->respondPaginated($withdrawals);
    }

    public function statistics(Request $request)
    {
        $this->authorize('view-withdrawals');

        $stats = $this->service->getStatistics($request->all());

        return $this->respond($stats);
    }

    public function getStatusOptions()
    {
        $this->authorize('view-withdrawals');

        return $this->respond(Withdrawal::getStatusOptions());
    }

    public function calculateFee(Request $request)
    {
        $this->authorize('view-withdrawals');

        $validated = $request->validate([
            'request_amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'withdrawal_method' => 'nullable|string|max:50',
            'user_level' => 'nullable|string|max:30',
        ]);

        $userLevel = $validated['user_level'] ?? $request->user()->level;
        $currency = $validated['currency'] ?? 'CNY';
        $method = $validated['withdrawal_method'] ?? 'bank_transfer';
        $amount = (float) $validated['request_amount'];

        $rule = $this->service->getApplicableRule($userLevel, $currency, $method);

        if (!$rule) {
            return $this->respondError('未找到适用的提现规则', 40401, 404);
        }

        if ($amount < $rule->min_amount) {
            return $this->respondError("提现金额不能低于最低限额: {$rule->min_amount} {$currency}", 42202, 422);
        }

        if ($amount > $rule->max_amount) {
            return $this->respondError("提现金额不能超过最高限额: {$rule->max_amount} {$currency}", 42202, 422);
        }

        $fee = $this->service->calculateFee($amount, $rule);

        return $this->respond([
            'request_amount' => $amount,
            'fee_rate' => $rule->fee_rate,
            'fee_amount' => $fee,
            'actual_amount' => $amount - $fee,
            'currency' => $currency,
            'withdrawal_method' => $method,
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'require_approval' => $rule->require_approval || $amount >= $rule->approval_threshold,
            'settlement_days' => $rule->settlement_days,
        ]);
    }

    public function apply(Request $request)
    {
        $this->authorize('apply-withdrawal');

        $validated = $request->validate([
            'request_amount' => 'required|numeric|min:0.01',
            'bank_card_id' => 'required|exists:bank_cards,id',
            'currency' => 'nullable|string|max:10',
            'withdrawal_method' => 'nullable|string|max:50',
            'remark' => 'nullable|string|max:500',
        ]);

        $withdrawal = $this->service->applyWithdrawal($request->user(), $validated);

        return $this->respondCreated($withdrawal, '提现申请提交成功');
    }

    public function show(Withdrawal $withdrawal)
    {
        $this->authorize('view-withdrawals');

        if (!$request->user()->can('manage-withdrawals') && $withdrawal->user_id !== $request->user()->id) {
            abort(403, '无权查看该提现记录');
        }

        return $this->respond($withdrawal->load(['user', 'wallet', 'rule', 'bankCard', 'approver', 'processor']));
    }

    public function approve(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('approve-withdrawal');

        $validated = $request->validate([
            'remark' => 'nullable|string|max:500',
        ]);

        $withdrawal = $this->service->approve($withdrawal, $validated['remark'] ?? '', $request->user()?->id);

        return $this->respond($withdrawal, '审核通过');
    }

    public function reject(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('approve-withdrawal');

        $validated = $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        $withdrawal = $this->service->reject($withdrawal, $validated['reject_reason'], $request->user()?->id);

        return $this->respond($withdrawal, '审核拒绝');
    }

    public function process(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('process-withdrawal');

        $validated = $request->validate([
            'processing_note' => 'nullable|string|max:500',
            'transaction_id' => 'nullable|string|max:100',
            'third_party_no' => 'nullable|string|max:100',
        ]);

        $withdrawal = $this->service->process($withdrawal, $validated, $request->user()?->id);

        return $this->respond($withdrawal, '已开始处理打款');
    }

    public function complete(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('process-withdrawal');

        $validated = $request->validate([
            'transaction_id' => 'nullable|string|max:100',
            'third_party_no' => 'nullable|string|max:100',
            'remark' => 'nullable|string|max:500',
        ]);

        $withdrawal = $this->service->complete($withdrawal, $validated, $request->user()?->id);

        return $this->respond($withdrawal, '打款完成');
    }

    public function fail(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('process-withdrawal');

        $validated = $request->validate([
            'fail_reason' => 'required|string|max:500',
        ]);

        $withdrawal = $this->service->fail($withdrawal, $validated['fail_reason'], $request->user()?->id);

        return $this->respond($withdrawal, '打款失败，资金已退回');
    }

    public function cancel(Request $request, Withdrawal $withdrawal)
    {
        $this->authorize('cancel-withdrawal');

        if ($withdrawal->user_id !== $request->user()->id && !$request->user()->can('manage-withdrawals')) {
            abort(403, '无权取消该提现申请');
        }

        $validated = $request->validate([
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $withdrawal = $this->service->cancel($withdrawal, $validated['cancel_reason'] ?? '');

        return $this->respond($withdrawal, '提现申请已取消');
    }

    public function batchApprove(Request $request)
    {
        $this->authorize('approve-withdrawal');

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:withdrawals,id',
            'remark' => 'nullable|string|max:500',
        ]);

        $results = $this->service->batchApprove(
            $validated['ids'],
            $validated['remark'] ?? '',
            $request->user()?->id
        );

        return $this->respond($results, sprintf(
            '批量审核完成，成功 %d 条，失败 %d 条',
            count($results['success']),
            count($results['failed'])
        ));
    }

    public function batchProcess(Request $request)
    {
        $this->authorize('process-withdrawal');

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:withdrawals,id',
            'processing_note' => 'nullable|string|max:500',
        ]);

        $results = $this->service->batchProcess(
            $validated['ids'],
            $validated,
            $request->user()?->id
        );

        return $this->respond($results, sprintf(
            '批量处理完成，成功 %d 条，失败 %d 条',
            count($results['success']),
            count($results['failed'])
        ));
    }
}
