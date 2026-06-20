<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\BusinessException;
use App\Exceptions\ForbiddenException;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\WithdrawRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Distributor;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\PermissionService;
use App\Services\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentRepository $paymentRepository,
        protected PermissionService $permissionService,
        protected WithdrawService $withdrawService,
    ) {
        $this->middleware('permission:payment.view')->only(['index', 'show']);
        $this->middleware('permission:payment.create')->only(['store', 'recharge', 'withdraw']);
        $this->middleware('permission:payment.delete')->only(['destroy']);
        $this->middleware('permission:payment.settle')->only(['settle', 'approveWithdraw', 'rejectWithdraw']);
        $this->middleware('permission:payment.refund')->only(['refund']);
    }

    public function index(Request $request)
    {
        $with = ['order:id,order_no,total,payment_status', 'creator:id,name', 'distributor:id,name,balance'];
        $paginator = $this->paymentRepository->listForUser($request->user(), $request, $with);

        return PaymentResource::collection($paginator);
    }

    public function store(PaymentRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $paymentType = PaymentType::tryFrom($data['type']);

        if (!$paymentType) {
            throw BusinessException::withCode(
                '无效的付款类型',
                'INVALID_PAYMENT_TYPE',
                [
                    'allowed' => array_column(PaymentType::cases(), 'value'),
                ]
            );
        }

        $this->permissionService->forUser($user)->ensureCanCreatePayment($paymentType);

        $distributorId = $data['distributor_id'] ?? null;
        if ($paymentType === PaymentType::RECHARGE) {
            if (!$distributorId && $user->isDistributor()) {
                $distributorId = $user->distributor_id;
            }
            $this->permissionService->forUser($user)->ensureCanRechargeForDistributor($distributorId);
        }

        if ($paymentType === PaymentType::ESCROW_DEPOSIT && !$distributorId) {
            $distributorId = $user->isDistributor() ? $user->distributor_id : null;
        }

        if ($paymentType === PaymentType::ESCROW_DEPOSIT) {
            $checkResult = $this->checkDistributorBalance($distributorId, (float) $data['amount']);
            if (!$checkResult['sufficient']) {
                $payment = $this->createFailedPayment($data, $user, $distributorId, 'INSUFFICIENT_BALANCE');

                return (new PaymentResource($payment->load(['order', 'creator', 'distributor'])))
                    ->additional([
                        'insufficient_balance' => true,
                        'current_balance' => $checkResult['current_balance'],
                        'deficit' => $checkResult['deficit'],
                        'message' => "余额不足，当前余额 {$checkResult['current_balance']}，还差 {$checkResult['deficit']}",
                    ])
                    ->response()
                    ->setStatusCode(402);
            }
        }

        $payment = Payment::create([
            'payment_no' => $this->generatePaymentNo($paymentType),
            'order_id' => $data['order_id'] ?? null,
            'distributor_id' => $distributorId,
            'created_by' => $user->id,
            'type' => $paymentType->value,
            'method' => $data['method'],
            'amount' => $data['amount'],
            'fee_amount' => $data['fee_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'CNY',
            'payment_date' => $data['payment_date'],
            'transaction_no' => $data['transaction_no'] ?? null,
            'status' => ($data['status'] ?? PaymentStatus::COMPLETED->value),
            'remark' => $data['remark'] ?? null,
            'fail_reason' => null,
        ]);

        return new PaymentResource($payment->load(['order', 'creator', 'distributor']));
    }

    public function show(Request $request, Payment $payment)
    {
        $this->paymentRepository->findForUserOrFail($request->user(), $payment->id, ['order', 'creator', 'distributor']);

        return new PaymentResource($payment->load(['order', 'creator', 'distributor']));
    }

    public function destroy(Request $request, Payment $payment)
    {
        $this->paymentRepository->findForUserOrFail($request->user(), $payment->id);

        if ($payment->isCompleted() && !$request->user()->isPlatform()) {
            throw new ForbiddenException('已完成的付款记录仅允许平台管理员删除');
        }

        $payment->delete();

        return response()->json(['message' => '删除成功']);
    }

    public function settle(Request $request, Payment $payment)
    {
        $user = $request->user();
        $this->paymentRepository->findForUserOrFail($user, $payment->id);

        $this->permissionService->forUser($user)->ensureCanSettlePayment($payment);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string'],
        ]);

        if ($validated['amount'] > (float) $payment->amount) {
            throw BusinessException::withCode(
                '结算金额不能大于原始托管金额',
                'SETTLE_AMOUNT_EXCEEDED'
            );
        }

        $release = Payment::create([
            'payment_no' => $this->generatePaymentNo(PaymentType::ESCROW_RELEASE),
            'order_id' => $payment->order_id,
            'distributor_id' => $payment->distributor_id,
            'created_by' => $user->id,
            'type' => PaymentType::ESCROW_RELEASE->value,
            'method' => $payment->method,
            'amount' => $validated['amount'],
            'fee_amount' => 0,
            'currency' => $payment->currency,
            'payment_date' => now()->toDateString(),
            'transaction_no' => $validated['transaction_no'] ?? null,
            'status' => PaymentStatus::COMPLETED->value,
            'remark' => $validated['remark'] ?? '平台结算给供应商',
        ]);

        return new PaymentResource($release->load(['order', 'creator', 'distributor']));
    }

    public function refund(Request $request, Payment $payment)
    {
        $user = $request->user();
        $this->paymentRepository->findForUserOrFail($user, $payment->id);

        $this->permissionService->forUser($user)->ensureCanRefundPayment($payment);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string'],
        ]);

        if ($validated['amount'] > (float) $payment->amount) {
            throw BusinessException::withCode(
                '退款金额不能大于原始付款金额',
                'REFUND_AMOUNT_EXCEEDED'
            );
        }

        $refund = Payment::create([
            'payment_no' => $this->generatePaymentNo(PaymentType::REFUND),
            'order_id' => $payment->order_id,
            'distributor_id' => $payment->distributor_id,
            'created_by' => $user->id,
            'type' => PaymentType::REFUND->value,
            'method' => $payment->method,
            'amount' => $validated['amount'],
            'fee_amount' => 0,
            'currency' => $payment->currency,
            'payment_date' => now()->toDateString(),
            'transaction_no' => $validated['transaction_no'] ?? null,
            'status' => PaymentStatus::COMPLETED->value,
            'remark' => $validated['remark'] ?? '平台退款给分销商',
        ]);

        return new PaymentResource($refund->load(['order', 'creator', 'distributor']));
    }

    public function recharge(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'distributor_id' => ['nullable', 'exists:distributors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,alipay,wechat,credit,other'],
            'currency' => ['nullable', 'string', 'max:10'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string'],
        ]);

        $distributorId = $validated['distributor_id'] ?? ($user->isDistributor() ? $user->distributor_id : null);

        $this->permissionService->forUser($user)->ensureCanRechargeForDistributor($distributorId);

        if (!$distributorId) {
            throw BusinessException::withCode(
                '请指定充值的分销商',
                'DISTRIBUTOR_REQUIRED'
            );
        }

        $distributor = Distributor::find($distributorId);
        if (!$distributor) {
            throw BusinessException::withCode(
                '分销商不存在',
                'DISTRIBUTOR_NOT_FOUND'
            );
        }

        $payment = DB::transaction(function () use ($validated, $user, $distributorId) {
            return Payment::create([
                'payment_no' => $this->generatePaymentNo(PaymentType::RECHARGE),
                'order_id' => null,
                'distributor_id' => $distributorId,
                'created_by' => $user->id,
                'type' => PaymentType::RECHARGE->value,
                'method' => $validated['method'],
                'amount' => $validated['amount'],
                'fee_amount' => 0,
                'currency' => $validated['currency'] ?? 'CNY',
                'payment_date' => now()->toDateString(),
                'transaction_no' => $validated['transaction_no'] ?? null,
                'status' => PaymentStatus::COMPLETED->value,
                'remark' => $validated['remark'] ?? '余额充值',
            ]);
        });

        $distributor->refresh();

        return (new PaymentResource($payment->load(['distributor', 'creator'])))
            ->additional([
                'new_balance' => (float) $distributor->balance,
                'message' => '充值成功',
            ]);
    }

    public function retry(Request $request, Payment $payment)
    {
        $user = $request->user();

        $this->paymentRepository->findForUserOrFail($user, $payment->id);

        $this->permissionService->forUser($user)->ensureCanRetryPayment($payment);

        $distributorId = $payment->distributor_id ?? ($payment->order?->distributor_id);

        $result = DB::transaction(function () use ($payment, $distributorId) {
            $distributor = Distributor::find($distributorId);
            $currentBalance = $distributor ? (float) $distributor->balance : 0;
            $deficit = $distributor ? $distributor->getBalanceDeficit((float) $payment->amount) : 0;

            if ($deficit > 0) {
                return [
                    'success' => false,
                    'current_balance' => $currentBalance,
                    'deficit' => $deficit,
                ];
            }

            $payment->status = PaymentStatus::COMPLETED->value;
            $payment->fail_reason = null;
            $payment->save();

            $distributor?->refresh();

            return [
                'success' => true,
                'current_balance' => $currentBalance,
                'new_balance' => $distributor ? (float) $distributor->balance : $currentBalance,
            ];
        });

        if (!$result['success']) {
            return response()->json([
                'message' => '重试失败，余额仍然不足',
                'insufficient_balance' => true,
                'current_balance' => $result['current_balance'],
                'deficit' => $result['deficit'],
                'failed_payment_id' => $payment->id,
            ], 402);
        }

        $payment->refresh();

        return (new PaymentResource($payment->load(['order', 'creator', 'distributor'])))
            ->additional([
                'message' => '支付重试成功',
                'new_balance' => $result['new_balance'],
            ]);
    }

    public function balance(Request $request)
    {
        $user = $request->user();

        $distributorId = $request->input('distributor_id');

        if ($distributorId) {
            $this->permissionService->forUser($user)->ensureCanRechargeForDistributor((int) $distributorId);
        } elseif ($user->isDistributor()) {
            $distributorId = $user->distributor_id;
        } else {
            throw new ForbiddenException('请指定分销商ID');
        }

        $distributor = Distributor::findOrFail($distributorId);

        return response()->json([
            'distributor_id' => $distributor->id,
            'distributor_name' => $distributor->name,
            'balance' => (float) $distributor->balance,
            'credit_limit' => (float) $distributor->credit_limit,
            'available_credit' => (float) bcsub((string) $distributor->credit_limit, (string) $distributor->balance, 2),
        ]);
    }

    public function withdrawRules(Request $request)
    {
        $user = $request->user();
        $distributorId = $user->isDistributor() ? $user->distributor_id : $request->input('distributor_id');

        $result = $this->withdrawService->getRulesWithDefaults($distributorId);

        $exampleAmount = $result['data']['quick_amounts'][1] ?? $result['defaults']['quick_amounts'][1] ?? 1000;
        $exampleFee = $this->withdrawService->calculateFee($exampleAmount);
        $result['data']['calculator'] = [
            'example_amount' => $exampleAmount,
            'example_fee' => $exampleFee,
            'example_receive' => $exampleAmount - $exampleFee,
        ];

        return response()->json($result);
    }

    public function withdraw(WithdrawRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $distributorId = $validated['distributor_id'] ?? ($user->isDistributor() ? $user->distributor_id : null);

        if (!$distributorId) {
            throw BusinessException::withCode(
                '请指定提现的分销商',
                'DISTRIBUTOR_REQUIRED'
            );
        }

        $this->permissionService->forUser($user)->ensureCanRechargeForDistributor($distributorId);

        $payment = $this->withdrawService->createWithdraw(
            array_merge($validated, ['distributor_id' => $distributorId]),
            $user
        );

        $distributor = Distributor::find($distributorId);
        $feeAmount = (float) $payment->fee_amount;
        $amount = (float) $payment->amount;
        $needAudit = $payment->isPending();

        return (new PaymentResource($payment->load(['distributor', 'creator'])))
            ->additional([
                'fee_amount' => $feeAmount,
                'actual_amount' => $amount - $feeAmount,
                'need_audit' => $needAudit,
                'message' => $needAudit ? '提现申请已提交，等待审核' : '提现成功',
                'new_balance' => $distributor?->balance ?? 0,
            ]);
    }

    public function approveWithdraw(Request $request, Payment $payment)
    {
        $user = $request->user();

        $this->permissionService->forUser($user)->ensureCanSettlePayment($payment);

        $validated = $request->validate([
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string'],
        ]);

        $payment = $this->withdrawService->approveWithdraw($payment, $user, $validated);

        return (new PaymentResource($payment->fresh()->load(['distributor', 'creator', 'auditor'])))
            ->additional([
                'message' => '提现审核通过',
            ]);
    }

    public function rejectWithdraw(Request $request, Payment $payment)
    {
        $user = $request->user();

        $this->permissionService->forUser($user)->ensureCanSettlePayment($payment);

        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:500'],
        ]);

        $payment = $this->withdrawService->rejectWithdraw($payment, $user, $validated['reject_reason']);

        return (new PaymentResource($payment->fresh()->load(['distributor', 'creator', 'auditor'])))
            ->additional([
                'message' => '提现申请已驳回',
            ]);
    }

    public function pendingWithdraws(Request $request)
    {
        $user = $request->user();

        $this->permissionService->forUser($user)->requirePlatformUser();

        $query = Payment::pendingWithdraws()->with(['distributor', 'creator']);

        if ($request->filled('distributor_id')) {
            $query->where('distributor_id', $request->input('distributor_id'));
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return PaymentResource::collection($paginator);
    }

    public function withdrawSummary(Request $request)
    {
        $user = $request->user();

        $distributorId = $request->input('distributor_id');
        if (!$distributorId && $user->isDistributor()) {
            $distributorId = $user->distributor_id;
        }

        if (!$distributorId) {
            throw BusinessException::withCode(
                '请指定分销商',
                'DISTRIBUTOR_REQUIRED'
            );
        }

        $this->permissionService->forUser($user)->ensureCanRechargeForDistributor((int) $distributorId);

        $summary = $this->withdrawService->getWithdrawSummary((int) $distributorId);

        return response()->json([
            'data' => $summary,
        ]);
    }

    protected function checkDistributorBalance(?int $distributorId, float $amount): array
    {
        if (!$distributorId) {
            return [
                'sufficient' => true,
                'current_balance' => 0,
                'deficit' => 0,
            ];
        }

        $distributor = Distributor::find($distributorId);
        if (!$distributor) {
            return [
                'sufficient' => true,
                'current_balance' => 0,
                'deficit' => 0,
            ];
        }

        $currentBalance = (float) $distributor->balance;
        $deficit = $distributor->getBalanceDeficit($amount);

        return [
            'sufficient' => $deficit <= 0,
            'current_balance' => $currentBalance,
            'deficit' => $deficit,
        ];
    }

    protected function createFailedPayment(array $data, $user, ?int $distributorId, string $failReason): Payment
    {
        return Payment::create([
            'payment_no' => $this->generatePaymentNo(PaymentType::tryFrom($data['type'])),
            'order_id' => $data['order_id'] ?? null,
            'distributor_id' => $distributorId,
            'created_by' => $user->id,
            'type' => $data['type'],
            'method' => $data['method'],
            'amount' => $data['amount'],
            'fee_amount' => $data['fee_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'CNY',
            'payment_date' => $data['payment_date'],
            'transaction_no' => $data['transaction_no'] ?? null,
            'status' => PaymentStatus::FAILED->value,
            'remark' => $data['remark'] ?? null,
            'fail_reason' => $failReason,
        ]);
    }

    protected function generatePaymentNo(?PaymentType $type): string
    {
        $prefix = match ($type) {
            PaymentType::ESCROW_RELEASE => 'STL',
            PaymentType::REFUND => 'RFD',
            PaymentType::PLATFORM_FEE => 'FEE',
            PaymentType::RECHARGE => 'RCH',
            PaymentType::WITHDRAW => 'WTH',
            PaymentType::WITHDRAW_FEE => 'WTF',
            default => 'PAY',
        };

        return $prefix.date('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
