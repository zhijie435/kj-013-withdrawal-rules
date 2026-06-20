<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\WithdrawException;
use App\Models\Distributor;
use App\Models\Payment;
use App\Models\ShearerlineConfig;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawService
{
    public function getRules(?int $distributorId = null): array
    {
        $withdrawConfig = ShearerlineConfig::getWithdrawConfig();
        $defaults = ShearerlineConfig::getWithdrawDefaults();

        foreach ($defaults as $key => $value) {
            if (!isset($withdrawConfig[$key])) {
                $withdrawConfig[$key] = $value;
            }
        }

        $availableBalance = 0;
        $maxWithdrawable = 0;

        if ($distributorId) {
            $distributor = Distributor::find($distributorId);
            if ($distributor) {
                $availableBalance = (float) $distributor->balance;
                $maxWithdrawable = max(0, $availableBalance - $withdrawConfig['min_balance_keep']);
            }
        }

        $withdrawConfig['available_balance'] = $availableBalance;
        $withdrawConfig['max_withdrawable'] = $maxWithdrawable > 0
            ? min($maxWithdrawable, $withdrawConfig['max_amount'])
            : $withdrawConfig['max_amount'];

        return $withdrawConfig;
    }

    public function getRulesWithDefaults(?int $distributorId = null): array
    {
        return [
            'data' => $this->getRules($distributorId),
            'defaults' => ShearerlineConfig::getWithdrawDefaults(),
        ];
    }

    public function calculateFee(float $amount): float
    {
        $feeRate = ShearerlineConfig::getWithdrawRule('fee_rate');
        $feeMin = ShearerlineConfig::getWithdrawRule('fee_min');
        $feeMax = ShearerlineConfig::getWithdrawRule('fee_max');

        $fee = $amount * ($feeRate / 100);

        if ($fee < $feeMin) {
            $fee = $feeMin;
        }

        if ($feeMax > 0 && $fee > $feeMax) {
            $fee = $feeMax;
        }

        return round($fee, 2);
    }

    public function validateWithdraw(float $amount, string $method, int $distributorId): array
    {
        $rules = $this->getRules($distributorId);

        if (!$rules['enabled']) {
            throw WithdrawException::disabled();
        }

        if ($amount < $rules['min_amount']) {
            throw WithdrawException::belowMin($rules['min_amount']);
        }

        if ($amount > $rules['max_amount']) {
            throw WithdrawException::aboveMax($rules['max_amount']);
        }

        if (!in_array($method, $rules['allow_methods'], true)) {
            throw WithdrawException::invalidMethod($rules['allow_methods']);
        }

        $distributor = Distributor::findOrFail($distributorId);
        $balance = (float) $distributor->balance;

        if ($amount + $rules['min_balance_keep'] > $balance) {
            throw WithdrawException::insufficientBalance($balance, $rules['min_balance_keep']);
        }

        $todayWithdrawn = (float) Payment::todayWithdraws($distributorId)->sum('amount');
        if ($rules['daily_limit'] > 0 && $todayWithdrawn + $amount > $rules['daily_limit']) {
            throw WithdrawException::dailyLimitExceeded($rules['daily_limit'], $todayWithdrawn);
        }

        $monthWithdrawn = (float) Payment::monthWithdraws($distributorId)->sum('amount');
        if ($rules['monthly_limit'] > 0 && $monthWithdrawn + $amount > $rules['monthly_limit']) {
            throw WithdrawException::monthlyLimitExceeded($rules['monthly_limit'], $monthWithdrawn);
        }

        return [
            'valid' => true,
            'fee_amount' => $this->calculateFee($amount),
            'need_audit' => $rules['require_audit'] && $amount >= $rules['audit_threshold'],
        ];
    }

    public function createWithdraw(array $data, User $user): Payment
    {
        $distributorId = $data['distributor_id'] ?? ($user->isDistributor() ? $user->distributor_id : null);

        if (!$distributorId) {
            throw BusinessException::withCode(
                '请指定提现的分销商',
                'DISTRIBUTOR_REQUIRED'
            );
        }

        $amount = (float) $data['amount'];
        $method = $data['method'];

        $validation = $this->validateWithdraw($amount, $method, $distributorId);
        $feeAmount = $validation['fee_amount'];
        $needAudit = $validation['need_audit'];
        $initialStatus = $needAudit ? PaymentStatus::PENDING : PaymentStatus::COMPLETED;

        return DB::transaction(function () use (
            $data,
            $user,
            $distributorId,
            $amount,
            $feeAmount,
            $method,
            $initialStatus
        ) {
            $payment = Payment::create([
                'payment_no' => $this->generatePaymentNo(PaymentType::WITHDRAW),
                'order_id' => null,
                'distributor_id' => $distributorId,
                'created_by' => $user->id,
                'type' => PaymentType::WITHDRAW->value,
                'method' => $method,
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'currency' => $data['currency'] ?? 'CNY',
                'payment_date' => now()->toDateString(),
                'transaction_no' => null,
                'status' => $initialStatus->value,
                'remark' => $data['remark'] ?? null,
                'fail_reason' => null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'account_name' => $data['account_name'] ?? null,
                'alipay_account' => $data['alipay_account'] ?? null,
                'wechat_account' => $data['wechat_account'] ?? null,
            ]);

            if ($feeAmount > 0) {
                Payment::create([
                    'payment_no' => $this->generatePaymentNo(PaymentType::WITHDRAW_FEE),
                    'order_id' => null,
                    'distributor_id' => $distributorId,
                    'created_by' => $user->id,
                    'type' => PaymentType::WITHDRAW_FEE->value,
                    'method' => $method,
                    'amount' => $feeAmount,
                    'fee_amount' => 0,
                    'currency' => $data['currency'] ?? 'CNY',
                    'payment_date' => now()->toDateString(),
                    'transaction_no' => null,
                    'status' => $initialStatus->value,
                    'remark' => "提现手续费 #{$payment->payment_no}",
                    'fail_reason' => null,
                ]);
            }

            if ($initialStatus === PaymentStatus::COMPLETED) {
                $distributor = Distributor::find($distributorId);
                $distributor?->decrementBalance($amount + $feeAmount);
            }

            return $payment;
        });
    }

    public function approveWithdraw(Payment $payment, User $auditor, array $data = []): Payment
    {
        if (!$payment->isWithdrawPrincipal()) {
            throw WithdrawException::notWithdrawRecord();
        }

        if (!$payment->isPending()) {
            throw WithdrawException::notPending();
        }

        return DB::transaction(function () use ($payment, $auditor, $data) {
            $payment->status = PaymentStatus::COMPLETED->value;
            $payment->audit_by = $auditor->id;
            $payment->audit_at = now();
            $payment->audit_remark = $data['remark'] ?? null;
            if (isset($data['transaction_no'])) {
                $payment->transaction_no = $data['transaction_no'];
            }
            $payment->save();

            $feePayment = Payment::where('type', PaymentType::WITHDRAW_FEE->value)
                ->where('remark', 'like', "%#{$payment->payment_no}%")
                ->where('status', PaymentStatus::PENDING->value)
                ->first();

            if ($feePayment) {
                $feePayment->status = PaymentStatus::COMPLETED->value;
                $feePayment->audit_by = $auditor->id;
                $feePayment->audit_at = now();
                $feePayment->save();
            }

            if ($payment->distributor) {
                $totalDeduct = (float) $payment->amount + (float) $payment->fee_amount;
                $payment->distributor->decrementBalance($totalDeduct);
            }

            return $payment;
        });
    }

    public function rejectWithdraw(Payment $payment, User $auditor, string $rejectReason): Payment
    {
        if (!$payment->isWithdrawPrincipal()) {
            throw WithdrawException::notWithdrawRecord();
        }

        if (!$payment->isPending()) {
            throw WithdrawException::notPending();
        }

        return DB::transaction(function () use ($payment, $auditor, $rejectReason) {
            $payment->status = PaymentStatus::FAILED->value;
            $payment->fail_reason = 'REJECTED: ' . $rejectReason;
            $payment->audit_by = $auditor->id;
            $payment->audit_at = now();
            $payment->audit_remark = $rejectReason;
            $payment->save();

            $feePayment = Payment::where('type', PaymentType::WITHDRAW_FEE->value)
                ->where('remark', 'like', "%#{$payment->payment_no}%")
                ->where('status', PaymentStatus::PENDING->value)
                ->first();

            if ($feePayment) {
                $feePayment->status = PaymentStatus::FAILED->value;
                $feePayment->fail_reason = 'REJECTED: ' . $rejectReason;
                $feePayment->audit_by = $auditor->id;
                $feePayment->audit_at = now();
                $feePayment->save();
            }

            return $payment;
        });
    }

    public function getPendingWithdrawCount(): int
    {
        return Payment::pendingWithdraws()->count();
    }

    public function getWithdrawSummary(int $distributorId): array
    {
        $todayAmount = (float) Payment::todayWithdraws($distributorId)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum('amount');

        $monthAmount = (float) Payment::monthWithdraws($distributorId)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum('amount');

        $totalAmount = (float) Payment::where('distributor_id', $distributorId)
            ->where('type', PaymentType::WITHDRAW->value)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum('amount');

        $totalFee = (float) Payment::where('distributor_id', $distributorId)
            ->where('type', PaymentType::WITHDRAW_FEE->value)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->sum('amount');

        return [
            'today_amount' => $todayAmount,
            'month_amount' => $monthAmount,
            'total_amount' => $totalAmount,
            'total_fee' => $totalFee,
            'pending_count' => Payment::where('distributor_id', $distributorId)
                ->where('type', PaymentType::WITHDRAW->value)
                ->where('status', PaymentStatus::PENDING->value)
                ->count(),
        ];
    }

    protected function generatePaymentNo(PaymentType $type): string
    {
        $prefix = match ($type) {
            PaymentType::WITHDRAW => 'WTH',
            PaymentType::WITHDRAW_FEE => 'WTF',
            default => 'PAY',
        };

        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
