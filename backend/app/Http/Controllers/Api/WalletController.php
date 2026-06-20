<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $service
    ) {}

    public function balance(Request $request)
    {
        $balance = $this->service->getBalance($request->user());

        return $this->success($balance);
    }

    public function distributorBalance(Request $request, Distributor $distributor)
    {
        if (!$request->user()->isPlatform() && $request->user()->distributor_id !== $distributor->id) {
            return $this->error('无权访问该分销商余额', 'PERMISSION_DENIED', [], 403);
        }

        $balance = $this->service->getDistributorBalance($distributor);

        return $this->success($balance);
    }

    public function transactions(Request $request)
    {
        if (!$request->user()->isDistributor() || !$request->user()->distributor) {
            return $this->error('用户不是分销商', 'USER_NOT_DISTRIBUTOR', [], 400);
        }

        $params = $request->all();
        $params['per_page'] = $this->perPage($request);

        $data = $this->service->getTransactions($request->user()->distributor, $params);

        return $this->success($data);
    }

    public function distributorTransactions(Request $request, Distributor $distributor)
    {
        if (!$request->user()->isPlatform()) {
            return $this->error('无权访问该分销商交易记录', 'PERMISSION_DENIED', [], 403);
        }

        $params = $request->all();
        $params['per_page'] = $this->perPage($request);

        $data = $this->service->getTransactions($distributor, $params);

        return $this->success($data);
    }

    public function statistics(Request $request)
    {
        if (!$request->user()->isDistributor() || !$request->user()->distributor) {
            return $this->error('用户不是分销商', 'USER_NOT_DISTRIBUTOR', [], 400);
        }

        $params = $request->only(['start_date', 'end_date']);
        $data = $this->service->getStatistics($request->user()->distributor, $params);

        return $this->success($data);
    }

    public function distributorStatistics(Request $request, Distributor $distributor)
    {
        if (!$request->user()->isPlatform()) {
            return $this->error('无权访问该分销商统计数据', 'PERMISSION_DENIED', [], 403);
        }

        $params = $request->only(['start_date', 'end_date']);
        $data = $this->service->getStatistics($distributor, $params);

        return $this->success($data);
    }

    public function adjustBalance(Request $request, Distributor $distributor)
    {
        if (!$request->user()->isPlatform()) {
            return $this->error('无权调整余额', 'PERMISSION_DENIED', [], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'remark' => 'required|string|max:500',
        ]);

        $amount = (float) $validated['amount'];

        if ($amount > 0) {
            $payment = $this->service->addBalance($distributor, $amount, $validated['remark'], $request->user()->id);
            $message = '余额增加成功';
        } else {
            $payment = $this->service->deductBalance($distributor, abs($amount), $validated['remark'], $request->user()->id);
            $message = '余额扣除成功';
        }

        return $this->success($payment, $message);
    }

    public function transfer(Request $request)
    {
        if (!$request->user()->isPlatform()) {
            return $this->error('无权进行转账操作', 'PERMISSION_DENIED', [], 403);
        }

        $validated = $request->validate([
            'from_distributor_id' => 'required|exists:distributors,id',
            'to_distributor_id' => 'required|exists:distributors,id|different:from_distributor_id',
            'amount' => 'required|numeric|min:0.01',
            'remark' => 'required|string|max:500',
        ]);

        $from = Distributor::findOrFail($validated['from_distributor_id']);
        $to = Distributor::findOrFail($validated['to_distributor_id']);

        $result = $this->service->transfer($from, $to, (float) $validated['amount'], $validated['remark'], $request->user()->id);

        return $this->success($result, '转账成功');
    }
}
