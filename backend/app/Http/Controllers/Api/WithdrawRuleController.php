<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WithdrawRule;
use App\Services\WithdrawRuleService;
use Illuminate\Http\Request;

class WithdrawRuleController extends Controller
{
    public function __construct(
        protected WithdrawRuleService $service
    ) {}

    public function index(Request $request)
    {
        $params = $request->all();
        $params['per_page'] = $this->perPage($request);

        $data = $this->service->getRules($params);

        return $this->success($data);
    }

    public function show(WithdrawRule $rule)
    {
        $rule = $this->service->getRule($rule->id);

        return $this->success($rule);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'user_level' => 'required|string|max:50',
            'withdraw_method_id' => 'required|exists:withdraw_methods,id',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|min:0',
            'daily_max_amount' => 'sometimes|numeric|min:0',
            'daily_max_count' => 'sometimes|integer|min:0',
            'monthly_max_amount' => 'sometimes|numeric|min:0',
            'monthly_max_count' => 'sometimes|integer|min:0',
            'fee_rate' => 'required|numeric|between:0,1',
            'fixed_fee' => 'sometimes|numeric|min:0',
            'min_fee' => 'sometimes|numeric|min:0',
            'max_fee' => 'sometimes|numeric|min:0',
            'processing_days' => 'sometimes|integer|min:0',
            'requires_audit' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
            'remark' => 'sometimes|string|max:500',
        ]);

        $rule = $this->service->createRule($validated, $request->user());

        return $this->success($rule, '规则创建成功');
    }

    public function update(Request $request, WithdrawRule $rule)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'user_level' => 'sometimes|string|max:50',
            'withdraw_method_id' => 'sometimes|exists:withdraw_methods,id',
            'min_amount' => 'sometimes|numeric|min:0',
            'max_amount' => 'sometimes|numeric|min:0',
            'daily_max_amount' => 'sometimes|numeric|min:0',
            'daily_max_count' => 'sometimes|integer|min:0',
            'monthly_max_amount' => 'sometimes|numeric|min:0',
            'monthly_max_count' => 'sometimes|integer|min:0',
            'fee_rate' => 'sometimes|numeric|between:0,1',
            'fixed_fee' => 'sometimes|numeric|min:0',
            'min_fee' => 'sometimes|numeric|min:0',
            'max_fee' => 'sometimes|numeric|min:0',
            'processing_days' => 'sometimes|integer|min:0',
            'requires_audit' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
            'remark' => 'sometimes|string|max:500',
        ]);

        $rule = $this->service->updateRule($rule, $validated, $request->user());

        return $this->success($rule, '规则更新成功');
    }

    public function destroy(WithdrawRule $rule)
    {
        $this->service->deleteRule($rule, request()->user());

        return $this->success(null, '规则删除成功');
    }

    public function toggleStatus(Request $request, WithdrawRule $rule)
    {
        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $rule = $this->service->toggleRuleStatus($rule, (bool) $validated['status'], $request->user());

        return $this->success($rule, $rule->status ? '规则已启用' : '规则已禁用');
    }

    public function enabled(Request $request)
    {
        $userLevel = $request->input('user_level');
        $rules = $this->service->getEnabledRules($userLevel);

        return $this->success($rules);
    }

    public function applicable(Request $request)
    {
        $validated = $request->validate([
            'withdraw_method_id' => 'required|exists:withdraw_methods,id',
        ]);

        $rule = $this->service->getApplicableRule($request->user(), (int) $validated['withdraw_method_id']);

        return $this->success($rule);
    }
}
