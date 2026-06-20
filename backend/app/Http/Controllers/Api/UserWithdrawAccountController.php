<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserWithdrawAccount;
use Illuminate\Http\Request;

class UserWithdrawAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = UserWithdrawAccount::where('user_id', $request->user()->id)
            ->with(['method']);

        if ($methodId = $request->input('withdraw_method_id')) {
            $query->where('withdraw_method_id', $methodId);
        }

        if ($status = $this->boolean($request, 'status')) {
            $query->where('status', $status);
        }

        $accounts = $query->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $this->success($accounts);
    }

    public function show(Request $request, UserWithdrawAccount $account)
    {
        if ($account->user_id !== $request->user()->id && !$request->user()->isPlatform()) {
            return $this->error('无权访问该账户', 'PERMISSION_DENIED', [], 403);
        }

        return $this->success($account->load(['method']));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'withdraw_method_id' => 'required|exists:withdraw_methods,id',
            'account_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:100',
            'bank_name' => 'sometimes|string|max:100',
            'bank_branch' => 'sometimes|string|max:100',
            'swift_code' => 'sometimes|string|max:50',
            'qr_code' => 'sometimes|string|max:255',
            'is_default' => 'sometimes|boolean',
            'remark' => 'sometimes|string|max:500',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = true;

        $account = UserWithdrawAccount::create($validated);

        return $this->success($account->load(['method']), '提现账户创建成功');
    }

    public function update(Request $request, UserWithdrawAccount $account)
    {
        if ($account->user_id !== $request->user()->id && !$request->user()->isPlatform()) {
            return $this->error('无权修改该账户', 'PERMISSION_DENIED', [], 403);
        }

        $validated = $request->validate([
            'account_name' => 'sometimes|string|max:100',
            'account_number' => 'sometimes|string|max:100',
            'bank_name' => 'sometimes|string|max:100',
            'bank_branch' => 'sometimes|string|max:100',
            'swift_code' => 'sometimes|string|max:50',
            'qr_code' => 'sometimes|string|max:255',
            'is_default' => 'sometimes|boolean',
            'status' => 'sometimes|boolean',
            'remark' => 'sometimes|string|max:500',
        ]);

        $account->update($validated);

        return $this->success($account->load(['method']), '提现账户更新成功');
    }

    public function destroy(Request $request, UserWithdrawAccount $account)
    {
        if ($account->user_id !== $request->user()->id && !$request->user()->isPlatform()) {
            return $this->error('无权删除该账户', 'PERMISSION_DENIED', [], 403);
        }

        $account->delete();

        return $this->success(null, '提现账户删除成功');
    }

    public function setDefault(Request $request, UserWithdrawAccount $account)
    {
        if ($account->user_id !== $request->user()->id && !$request->user()->isPlatform()) {
            return $this->error('无权操作该账户', 'PERMISSION_DENIED', [], 403);
        }

        $account->setAsDefault();

        return $this->success($account, '已设为默认账户');
    }
}
