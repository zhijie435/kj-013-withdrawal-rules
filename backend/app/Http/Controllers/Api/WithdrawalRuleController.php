<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRule;
use App\Services\WithdrawalRuleService;
use Illuminate\Http\Request;

class WithdrawalRuleController extends Controller
{
    public function __construct(protected WithdrawalRuleService $service)
    {
    }

    public function index(Request $request)
    {
        $this->authorize('view-withdrawal-rules');

        $rules = $this->service->getRuleList(array_merge([
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 15),
        ], $request->all()));

        return $this->respondPaginated($rules);
    }

    public function current(Request $request)
    {
        $this->authorize('view-withdrawal-rules');

        $userLevel = $request->input('user_level', $request->user()->level);
        $currency = $request->input('currency', 'CNY');
        $method = $request->input('withdrawal_method', 'bank_transfer');

        $rule = $this->service->getCurrentRule($userLevel, $currency, $method);

        if (!$rule) {
            return $this->respondError('未找到适用的提现规则', 40401, 404);
        }

        return $this->respond($rule);
    }

    public function getStatusOptions()
    {
        $this->authorize('view-withdrawal-rules');

        return $this->respond(WithdrawalRule::getStatusOptions());
    }

    public function getLevelOptions()
    {
        $this->authorize('view-withdrawal-rules');

        return $this->respond(WithdrawalRule::getLevelOptions());
    }

    public function getMethodOptions()
    {
        $this->authorize('view-withdrawal-rules');

        $options = WithdrawalRule::getMethodOptions();
        $result = [];
        foreach ($options as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $this->respond($result);
    }

    public function getCurrencyOptions()
    {
        $this->authorize('view-withdrawal-rules');

        $options = WithdrawalRule::getCurrencyOptions();
        $result = [];
        foreach ($options as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }

        return $this->respond($result);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-withdrawal-rules');

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:50|unique:withdrawal_rules,code',
            'user_level' => 'nullable|string|max:30',
            'currency' => 'nullable|string|max:10',
            'withdrawal_method' => 'nullable|string|max:50',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|numeric|min:0',
            'monthly_limit' => 'nullable|numeric|min:0',
            'fee_rate' => 'nullable|numeric|between:0,1',
            'fee_min' => 'nullable|numeric|min:0',
            'fee_max' => 'nullable|numeric|min:0',
            'settlement_days' => 'nullable|integer|min:0',
            'daily_max_count' => 'nullable|integer|min:1',
            'require_approval' => 'nullable|boolean',
            'approval_threshold' => 'nullable|numeric|min:0',
            'allowed_regions' => 'nullable|array',
            'denied_regions' => 'nullable|array',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ]);

        $rule = $this->service->createRule($validated, $request->user()?->id);

        return $this->respondCreated($rule, '提现规则创建成功');
    }

    public function show(WithdrawalRule $rule)
    {
        $this->authorize('view-withdrawal-rules');

        return $this->respond($rule->loadCount('withdrawals')->load(['creator', 'updater']));
    }

    public function update(Request $request, WithdrawalRule $rule)
    {
        $this->authorize('manage-withdrawal-rules');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'code' => 'sometimes|string|max:50|unique:withdrawal_rules,code,' . $rule->id,
            'user_level' => 'sometimes|string|max:30',
            'currency' => 'sometimes|string|max:10',
            'withdrawal_method' => 'sometimes|string|max:50',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'sometimes|numeric|min:0',
            'daily_limit' => 'sometimes|numeric|min:0',
            'monthly_limit' => 'sometimes|numeric|min:0',
            'fee_rate' => 'sometimes|numeric|between:0,1',
            'fee_min' => 'sometimes|numeric|min:0',
            'fee_max' => 'sometimes|numeric|min:0',
            'settlement_days' => 'sometimes|integer|min:0',
            'daily_max_count' => 'sometimes|integer|min:1',
            'require_approval' => 'sometimes|boolean',
            'approval_threshold' => 'sometimes|numeric|min:0',
            'allowed_regions' => 'sometimes|array',
            'denied_regions' => 'sometimes|array',
            'description' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
            'effective_from' => 'sometimes|date',
            'effective_to' => 'sometimes|date|after:effective_from',
        ]);

        $rule = $this->service->updateRule($rule, $validated, $request->user()?->id);

        return $this->respond($rule, '提现规则更新成功');
    }

    public function destroy(WithdrawalRule $rule)
    {
        $this->authorize('manage-withdrawal-rules');

        $this->service->deleteRule($rule);

        return $this->respond(null, '提现规则删除成功');
    }

    public function toggleActive(Request $request, WithdrawalRule $rule)
    {
        $this->authorize('manage-withdrawal-rules');

        $rule = $this->service->toggleActive($rule, $request->user()?->id);

        $message = $rule->is_active ? '规则已启用' : '规则已禁用';

        return $this->respond($rule, $message);
    }
}
