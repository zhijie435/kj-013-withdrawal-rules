<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getBalance(User $user): array
    {
        if (!$user->isDistributor() || !$user->distributor) {
            throw BusinessException::withCode('用户不是分销商', 'USER_NOT_DISTRIBUTOR');
        }

        $distributor = $user->distributor;

        $pendingAmount = (float) Payment::where('distributor_id', $distributor->id)
            ->whereIn('type', [\App\Enums\PaymentType::WITHDRAW->value])
            ->whereIn('status', [\App\Enums\PaymentStatus::PENDING->value, \App\Enums\PaymentStatus::APPROVED->value])
            ->sum('amount');

        $frozenAmount = $pendingAmount;

        return [
            'total_balance' => (float) $distributor->balance,
            'available_balance' => max(0, (float) $distributor->balance - $frozenAmount),
            'frozen_balance' => $frozenAmount,
            'pending_withdraw' => $pendingAmount,
            'currency' => 'CNY',
        ];
    }

    public function getDistributorBalance(Distributor $distributor): array
    {
        $pendingAmount = (float) Payment::where('distributor_id', $distributor->id)
            ->whereIn('type', [\App\Enums\PaymentType::WITHDRAW->value])
            ->whereIn('status', [\App\Enums\PaymentStatus::PENDING->value, \App\Enums\PaymentStatus::APPROVED->value])
            ->sum('amount');

        $frozenAmount = $pendingAmount;

        return [
            'total_balance' => (float) $distributor->balance,
            'available_balance' => max(0, (float) $distributor->balance - $frozenAmount),
            'frozen_balance' => $frozenAmount,
            'pending_withdraw' => $pendingAmount,
            'currency' => 'CNY',
        ];
    }

    public function checkSufficientBalance(Distributor $distributor, float $amount, float $keepMin = 0): bool
    {
        $balance = $this->getDistributorBalance($distributor);

        return ($balance['available_balance'] - $keepMin) >= $amount;
    }

    public function freezeBalance(Distributor $distributor, float $amount, string $reason = ''): bool
    {
        return true;
    }

    public function unfreezeBalance(Distributor $distributor, float $amount, string $reason = ''): bool
    {
        return true;
    }

    public function deductBalance(Distributor $distributor, float $amount, string $remark = '', ?int $operatorId = null): Payment
    {
        return DB::transaction(function () use ($distributor, $amount, $remark, $operatorId) {
            if (!$distributor->hasSufficientBalance($amount)) {
                throw BusinessException::withCode('余额不足', 'INSUFFICIENT_BALANCE');
            }

            $distributor->decrementBalance($amount);

            return Payment::create([
                'payment_no' => $this->generatePaymentNo('DED'),
                'distributor_id' => $distributor->id,
                'created_by' => $operatorId,
                'type' => \App\Enums\PaymentType::EXPENSE->value,
                'method' => 'balance',
                'amount' => -$amount,
                'fee_amount' => 0,
                'currency' => 'CNY',
                'payment_date' => now()->toDateString(),
                'status' => \App\Enums\PaymentStatus::COMPLETED->value,
                'remark' => $remark,
            ]);
        });
    }

    public function addBalance(Distributor $distributor, float $amount, string $remark = '', ?int $operatorId = null): Payment
    {
        return DB::transaction(function () use ($distributor, $amount, $remark, $operatorId) {
            $distributor->incrementBalance($amount);

            return Payment::create([
                'payment_no' => $this->generatePaymentNo('ADD'),
                'distributor_id' => $distributor->id,
                'created_by' => $operatorId,
                'type' => \App\Enums\PaymentType::INCOME->value,
                'method' => 'manual',
                'amount' => $amount,
                'fee_amount' => 0,
                'currency' => 'CNY',
                'payment_date' => now()->toDateString(),
                'status' => \App\Enums\PaymentStatus::COMPLETED->value,
                'remark' => $remark,
            ]);
        });
    }

    public function transfer(Distributor $from, Distributor $to, float $amount, string $remark = '', ?int $operatorId = null): array
    {
        return DB::transaction(function () use ($from, $to, $amount, $remark, $operatorId) {
            $deductPayment = $this->deductBalance($from, $amount, "转账给分销商#{$to->id}: {$remark}", $operatorId);
            $addPayment = $this->addBalance($to, $amount, "接收分销商#{$from->id}转账: {$remark}", $operatorId);

            return [
                'from_payment' => $deductPayment,
                'to_payment' => $addPayment,
            ];
        });
    }

    public function getTransactions(Distributor $distributor, array $params = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Payment::where('distributor_id', $distributor->id)
            ->with(['creator']);

        if (isset($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (isset($params['start_date'])) {
            $query->whereDate('created_at', '>=', $params['start_date']);
        }

        if (isset($params['end_date'])) {
            $query->whereDate('created_at', '<=', $params['end_date']);
        }

        return $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);
    }

    public function getStatistics(Distributor $distributor, array $params = []): array
    {
        $startDate = $params['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $params['end_date'] ?? now()->endOfMonth()->toDateString();

        $income = (float) Payment::where('distributor_id', $distributor->id)
            ->where('type', \App\Enums\PaymentType::INCOME->value)
            ->where('status', \App\Enums\PaymentStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $expense = abs((float) Payment::where('distributor_id', $distributor->id)
            ->where('type', \App\Enums\PaymentType::EXPENSE->value)
            ->where('status', \App\Enums\PaymentStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount'));

        $withdraw = (float) Payment::where('distributor_id', $distributor->id)
            ->where('type', \App\Enums\PaymentType::WITHDRAW->value)
            ->where('status', \App\Enums\PaymentStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $recharge = (float) Payment::where('distributor_id', $distributor->id)
            ->where('type', \App\Enums\PaymentType::RECHARGE->value)
            ->where('status', \App\Enums\PaymentStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'income' => $income,
            'expense' => $expense,
            'withdraw' => $withdraw,
            'recharge' => $recharge,
            'net_flow' => $income + $recharge - $expense - $withdraw,
        ];
    }

    protected function generatePaymentNo(string $prefix = 'PAY'): string
    {
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
