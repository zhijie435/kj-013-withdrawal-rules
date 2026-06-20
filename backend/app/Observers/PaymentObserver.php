<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Services\CrossBorderStatusService;
use Illuminate\Support\Facades\DB;

class PaymentObserver
{
    public function __construct(
        private CrossBorderStatusService $statusService,
    ) {}

    public function created(Payment $payment): void
    {
        if ($payment->isEscrowDeposit() || $payment->isRefund()) {
            $this->statusService->syncPaymentToOrder($payment);
        }

        if ($payment->isCompleted()) {
            $this->handleBalanceChange($payment);
        }
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged(['amount', 'type', 'order_id', 'status'])) {
            if ($payment->isEscrowDeposit() || $payment->isRefund()) {
                $this->statusService->syncPaymentToOrder($payment);
            }
        }

        if (
            $payment->wasChanged('status')
            && $payment->isCompleted()
            && $payment->getOriginal('status') !== PaymentStatus::COMPLETED->value
        ) {
            $this->handleBalanceChange($payment);
        }
    }

    public function deleted(Payment $payment): void
    {
        if ($payment->isEscrowDeposit() || $payment->isRefund()) {
            $this->statusService->syncPaymentToOrder($payment);
        }

        if ($payment->isCompleted()) {
            $this->reverseBalanceChange($payment);
        }
    }

    protected function handleBalanceChange(Payment $payment): void
    {
        $distributorId = $payment->distributor_id ?? ($payment->order?->distributor_id);
        if (!$distributorId) {
            return;
        }

        DB::transaction(function () use ($payment, $distributorId) {
            $distributor = \App\Models\Distributor::find($distributorId);
            if (!$distributor) {
                return;
            }

            $type = $payment->getTypeEnum();
            $amount = (float) $payment->amount;

            match ($type) {
                PaymentType::RECHARGE => $distributor->incrementBalance($amount),
                PaymentType::ESCROW_DEPOSIT => $distributor->decrementBalance($amount),
                PaymentType::REFUND => $distributor->incrementBalance($amount),
                default => null,
            };
        });
    }

    protected function reverseBalanceChange(Payment $payment): void
    {
        $distributorId = $payment->distributor_id ?? ($payment->order?->distributor_id);
        if (!$distributorId) {
            return;
        }

        DB::transaction(function () use ($payment, $distributorId) {
            $distributor = \App\Models\Distributor::find($distributorId);
            if (!$distributor) {
                return;
            }

            $type = $payment->getTypeEnum();
            $amount = (float) $payment->amount;

            match ($type) {
                PaymentType::RECHARGE => $distributor->decrementBalance($amount),
                PaymentType::ESCROW_DEPOSIT => $distributor->incrementBalance($amount),
                PaymentType::REFUND => $distributor->decrementBalance($amount),
                default => null,
            };
        });
    }
}
