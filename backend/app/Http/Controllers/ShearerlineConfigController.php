<?php

namespace App\Http\Controllers;

use App\Exceptions\ForbiddenException;
use App\Http\Requests\WithdrawConfigRequest;
use App\Services\WithdrawConfigService;
use Illuminate\Http\Request;

class ShearerlineConfigController extends Controller
{
    public function __construct(
        protected WithdrawConfigService $withdrawConfigService,
    ) {
        $this->middleware('auth:sanctum');
    }

    public function withdraw(Request $request)
    {
        $user = $request->user();
        $onlyPublic = !$user->isPlatform();

        $result = $this->withdrawConfigService->getAllWithDefaults($onlyPublic);

        return response()->json($result);
    }

    public function updateWithdraw(WithdrawConfigRequest $request)
    {
        $user = $request->user();

        if (!$user->isPlatform()) {
            throw new ForbiddenException('仅平台管理员可修改提现配置');
        }

        $validated = $request->validated();

        $config = $this->withdrawConfigService->update($validated);

        return response()->json([
            'message' => '提现规则更新成功',
            'data' => $config,
        ]);
    }

    public function publicWithdraw(Request $request)
    {
        $result = $this->withdrawConfigService->getAllWithDefaults(true);

        return response()->json([
            'data' => $result['data'],
        ]);
    }
}
