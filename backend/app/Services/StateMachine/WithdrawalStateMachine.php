<?php

namespace App\Services\StateMachine;

use App\Contracts\StateMachine\StateMachineInterface;
use App\Contracts\StateMachine\TransitionResult;
use App\Enums\WithdrawStatus;
use App\Models\Withdrawal;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WithdrawalStateMachine implements StateMachineInterface
{
    public function __construct(
        protected Withdrawal $withdrawal,
        protected ?User $operator = null
    ) {
    }

    public function getModel(): Model
    {
        return $this->withdrawal;
    }

    public function currentState(): BackedEnum
    {
        return WithdrawStatus::from($this->withdrawal->status);
    }

    public function canTransitionTo(BackedEnum $targetState, array $context = []): bool
    {
        if (!$targetState instanceof WithdrawStatus) {
            return false;
        }

        return $this->currentState()->canTransitionTo($targetState);
    }

    public function validateTransition(BackedEnum $targetState, array $context = []): TransitionResult
    {
        if (!$targetState instanceof WithdrawStatus) {
            return TransitionResult::failure('无效的提现状态类型');
        }

        $current = $this->currentState();

        if ($current === $targetState) {
            return TransitionResult::success('状态未变更');
        }

        if ($current->isFinal()) {
            return TransitionResult::failure("提现已处于终态（{$current->label()}），无法变更状态");
        }

        if (!$this->canTransitionTo($targetState, $context)) {
            $allowed = array_map(fn (WithdrawStatus $s) => $s->label(), $this->allowedTransitions());
            $allowedStr = $allowed ? implode('、', $allowed) : '无';

            return TransitionResult::failure(
                "不允许从「{$current->label()}」变更为「{$targetState->label()}」，允许的目标状态：{$allowedStr}"
            );
        }

        return $this->validateBusinessRules($targetState, $context);
    }

    protected function validateBusinessRules(WithdrawStatus $targetState, array $context): TransitionResult
    {
        switch ($targetState) {
            case WithdrawStatus::REJECTED:
                if (empty($context['reason'])) {
                    return TransitionResult::failure('驳回需填写原因');
                }
                break;

            case WithdrawStatus::FAILED:
                if (empty($context['reason'])) {
                    return TransitionResult::failure('失败需填写原因');
                }
                break;

            case WithdrawStatus::CANCELLED:
                $user = $context['user'] ?? $this->operator;
                if ($user && !$this->canCancelByUser($user)) {
                    return TransitionResult::failure('您无权取消该提现申请');
                }
                break;
        }

        return TransitionResult::success();
    }

    protected function canCancelByUser(User $user): bool
    {
        if ($user->isPlatform() || $user->can('manage-withdrawals')) {
            return true;
        }

        return $this->withdrawal->user_id === $user->id;
    }

    public function transitionTo(BackedEnum $targetState, array $context = []): Model
    {
        if (!$targetState instanceof WithdrawStatus) {
            throw new \InvalidArgumentException('无效的提现状态类型');
        }

        $validation = $this->validateTransition($targetState, $context);

        if ($validation->isInvalid()) {
            throw new \DomainException($validation->message);
        }

        return DB::transaction(function () use ($targetState, $context) {
            $this->updateStatusFields($targetState, $context);
            $this->addAuditLog($targetState, $context);

            return $this->withdrawal->fresh()->load(['user', 'wallet', 'rule', 'bankCard', 'approver', 'processor']);
        });
    }

    protected function updateStatusFields(WithdrawStatus $targetState, array $context): void
    {
        $updateData = ['status' => $targetState->value];
        $operatorId = $context['operator_id'] ?? $this->operator?->id ?? auth()->id();

        switch ($targetState) {
            case WithdrawStatus::APPROVED:
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = $operatorId;
                break;

            case WithdrawStatus::REJECTED:
                $updateData['reject_reason'] = $context['reason'] ?? '';
                break;

            case WithdrawStatus::PROCESSING:
                $updateData['processed_at'] = now();
                $updateData['processed_by'] = $operatorId;
                if (isset($context['processing_note'])) {
                    $updateData['processing_note'] = $context['processing_note'];
                }
                if (isset($context['transaction_id'])) {
                    $updateData['transaction_id'] = $context['transaction_id'];
                }
                if (isset($context['third_party_no'])) {
                    $updateData['third_party_no'] = $context['third_party_no'];
                }
                break;

            case WithdrawStatus::COMPLETED:
                $updateData['completed_at'] = now();
                if (isset($context['transaction_id'])) {
                    $updateData['transaction_id'] = $context['transaction_id'];
                }
                if (isset($context['third_party_no'])) {
                    $updateData['third_party_no'] = $context['third_party_no'];
                }
                break;

            case WithdrawStatus::FAILED:
                $updateData['fail_reason'] = $context['reason'] ?? '';
                break;

            case WithdrawStatus::CANCELLED:
                $updateData['cancel_reason'] = $context['reason'] ?? '';
                break;
        }

        $this->withdrawal->update($updateData);
    }

    protected function addAuditLog(WithdrawStatus $targetState, array $context): void
    {
        $actionMap = [
            WithdrawStatus::PENDING->value => 'submit',
            WithdrawStatus::APPROVED->value => 'approve',
            WithdrawStatus::REJECTED->value => 'reject',
            WithdrawStatus::PROCESSING->value => 'process',
            WithdrawStatus::COMPLETED->value => 'complete',
            WithdrawStatus::FAILED->value => 'fail',
            WithdrawStatus::CANCELLED->value => 'cancel',
        ];

        $action = $actionMap[$targetState->value] ?? $targetState->value;
        $remark = $context['remark'] ?? $context['reason'] ?? '';
        $userId = $context['operator_id'] ?? $this->operator?->id;

        $this->withdrawal->addAuditLog($action, $remark, $userId);
    }

    public function allowedTransitions(): array
    {
        return $this->currentState()->allowedTransitions();
    }
}
