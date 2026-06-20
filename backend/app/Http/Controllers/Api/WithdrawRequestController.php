<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WithdrawRequest;
use App\Services\WithdrawRequestService;
use Illuminate\Http\Request;

class WithdrawRequestController extends Controller
{
    public function __construct(
        protected WithdrawRequestService $service
    ) {}

    public function index(Request $request)
    {
        $params = $request->all();
        $params['per_page'] = $this->perPage($request);

        $data = $this->service->getRequests($params, $request->user());

        return $this->success($data);
    }

    public function show(Request $request, int $id)
    {
        $withdraw = $this->service->getRequest($id, $request->user());

        return $this->success($withdraw);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'withdraw_method_id' => 'required|exists:withdraw_methods,id',
            'account_id' => 'sometimes|exists:user_withdraw_accounts,id',
            'distributor_id' => 'sometimes|exists:distributors,id',
            'currency' => 'sometimes|string|max:10',
            'account_name' => 'sometimes|required_without:account_id|string|max:100',
            'account_number' => 'sometimes|required_without:account_id|string|max:100',
            'bank_name' => 'sometimes|string|max:100',
            'bank_branch' => 'sometimes|string|max:100',
            'swift_code' => 'sometimes|string|max:50',
            'alipay_account' => 'sometimes|string|max:100',
            'wechat_account' => 'sometimes|string|max:100',
            'remark' => 'sometimes|string|max:500',
            'min_balance_keep' => 'sometimes|numeric|min:0',
        ]);

        $withdraw = $this->service->createRequest($validated, $request->user());

        return $this->success($withdraw, '提现申请提交成功');
    }

    public function approve(Request $request, WithdrawRequest $withdraw)
    {
        $validated = $request->validate([
            'remark' => 'sometimes|string|max:500',
        ]);

        $withdraw = $this->service->approve($withdraw, $request->user(), $validated['remark'] ?? '');

        return $this->success($withdraw, '审核通过');
    }

    public function reject(Request $request, WithdrawRequest $withdraw)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $withdraw = $this->service->reject($withdraw, $request->user(), $validated['reason']);

        return $this->success($withdraw, '审核已驳回');
    }

    public function cancel(Request $request, WithdrawRequest $withdraw)
    {
        $withdraw = $this->service->cancel($withdraw, $request->user());

        return $this->success($withdraw, '已取消提现申请');
    }

    public function process(Request $request, WithdrawRequest $withdraw)
    {
        $validated = $request->validate([
            'transaction_no' => 'sometimes|string|max:100',
            'remark' => 'sometimes|string|max:500',
        ]);

        $withdraw = $this->service->process($withdraw, $request->user(), $validated);

        return $this->success($withdraw, '开始打款处理');
    }

    public function complete(Request $request, WithdrawRequest $withdraw)
    {
        $validated = $request->validate([
            'transaction_no' => 'sometimes|string|max:100',
            'remark' => 'sometimes|string|max:500',
        ]);

        $withdraw = $this->service->complete($withdraw, $request->user(), $validated);

        return $this->success($withdraw, '提现已完成');
    }

    public function fail(Request $request, WithdrawRequest $withdraw)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $withdraw = $this->service->fail($withdraw, $request->user(), $validated['reason']);

        return $this->success($withdraw, '已标记为失败');
    }

    public function batchApprove(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:withdraw_requests,id',
            'remark' => 'sometimes|string|max:500',
        ]);

        $result = $this->service->batchApprove($validated['ids'], $request->user(), $validated['remark'] ?? '');

        return $this->success($result, sprintf('批量审核完成：成功 %d 条，失败 %d 条', $result['success'], $result['failed']));
    }

    public function batchReject(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:withdraw_requests,id',
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->service->batchReject($validated['ids'], $request->user(), $validated['reason']);

        return $this->success($result, sprintf('批量驳回完成：成功 %d 条，失败 %d 条', $result['success'], $result['failed']));
    }

    public function statistics(Request $request)
    {
        $params = $request->only(['start_date', 'end_date']);

        if ($request->user()->isPlatform()) {
            $data = $this->service->getStatistics($params);
        } else {
            $data = $this->service->getUserStatistics($request->user(), $params);
        }

        return $this->success($data);
    }

    public function pendingCount(Request $request)
    {
        $count = $this->service->getPendingCount();

        return $this->success(['count' => $count]);
    }

    public function validateAmount(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'withdraw_method_id' => 'required|exists:withdraw_methods,id',
        ]);

        $result = app(\App\Services\WithdrawRuleService::class)->validateWithdrawAmount(
            $request->user(),
            (int) $validated['withdraw_method_id'],
            (float) $validated['amount']
        );

        return $this->success($result);
    }
}
