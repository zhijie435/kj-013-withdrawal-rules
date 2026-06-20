<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WithdrawMethod;
use Illuminate\Http\Request;

class WithdrawMethodController extends Controller
{
    public function index(Request $request)
    {
        $query = WithdrawMethod::query();

        if ($status = $this->boolean($request, 'status')) {
            $query->where('status', $status);
        }

        $methods = $query->ordered()->get();

        return $this->success($methods);
    }

    public function show(WithdrawMethod $method)
    {
        return $this->success($method);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:withdraw_methods,code',
            'name' => 'required|string|max:100',
            'icon' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:500',
            'currency' => 'sometimes|string|max:10',
            'sort' => 'sometimes|integer|min:0',
            'status' => 'sometimes|boolean',
            'config' => 'sometimes|array',
        ]);

        $method = WithdrawMethod::create($validated);

        return $this->success($method, '提现方式创建成功');
    }

    public function update(Request $request, WithdrawMethod $method)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'icon' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:500',
            'currency' => 'sometimes|string|max:10',
            'sort' => 'sometimes|integer|min:0',
            'status' => 'sometimes|boolean',
            'config' => 'sometimes|array',
        ]);

        $method->update($validated);

        return $this->success($method, '提现方式更新成功');
    }

    public function destroy(WithdrawMethod $method)
    {
        if ($method->rules()->exists()) {
            return $this->error('该提现方式已关联规则，无法删除', 'METHOD_HAS_RULES', [], 422);
        }

        if ($method->withdrawRequests()->exists()) {
            return $this->error('该提现方式已被使用，无法删除', 'METHOD_HAS_REQUESTS', [], 422);
        }

        $method->delete();

        return $this->success(null, '提现方式删除成功');
    }

    public function toggleStatus(Request $request, WithdrawMethod $method)
    {
        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $method->status = $validated['status'];
        $method->save();

        return $this->success($method, $method->status ? '提现方式已启用' : '提现方式已禁用');
    }

    public function enabled(Request $request)
    {
        $methods = WithdrawMethod::enabled()->ordered()->get();

        return $this->success($methods);
    }
}
