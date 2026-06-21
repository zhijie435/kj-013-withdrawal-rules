<?php

namespace App\Services;

use App\Exceptions\WithdrawalRule\InvalidWithdrawalAmountException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleConflictException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleInUseException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleNotFoundException;
use App\Models\WithdrawalRule;
use App\Repositories\WithdrawalRuleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WithdrawalRuleService
{
    public function __construct(
        protected WithdrawalRuleRepository $repository
    ) {
    }

    public function getRuleList(array $params = []): LengthAwarePaginator
    {
        return $this->repository->listPaginated($params);
    }

    public function getCurrentRule(string $userLevel = 'normal', string $currency = 'CNY', string $method = 'bank_transfer'): ?WithdrawalRule
    {
        return $this->repository->getApplicableRule($userLevel, $currency, $method);
    }

    public function getCurrentRuleOrFail(string $userLevel, string $currency, string $method): WithdrawalRule
    {
        $rule = $this->getCurrentRule($userLevel, $currency, $method);

        if (!$rule) {
            throw WithdrawalRuleNotFoundException::for($userLevel, $currency, $method);
        }

        return $rule;
    }

    public function getRuleById(int $id): WithdrawalRule
    {
        $rule = $this->repository->newModel()
            ->with(['creator', 'updater'])
            ->withCount('withdrawals')
            ->find($id);

        if (!$rule) {
            throw WithdrawalRuleNotFoundException::byId($id);
        }

        return $rule;
    }

    public function getActiveRules(): Collection
    {
        return $this->repository->getActiveRules();
    }

    public function createRule(array $data, ?int $creatorId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $this->ensureCodeUnique($data['code'] ?? null);

            $isActive = $data['is_active'] ?? false;
            $userLevel = $data['user_level'] ?? WithdrawalRule::LEVEL_NORMAL;
            $currency = $data['currency'] ?? 'CNY';
            $method = $data['withdrawal_method'] ?? 'bank_transfer';

            if ($isActive) {
                $this->repository->deactivateConflictingRules(
                    $userLevel,
                    $currency,
                    $method,
                    null,
                    $creatorId
                );
            }

            $rule = $this->repository->create(array_merge($data, [
                'created_by' => $creatorId,
                'updated_by' => $creatorId,
            ]));

            return $rule->fresh()->load(['creator', 'updater']);
        });
    }

    public function updateRule(WithdrawalRule $rule, array $data, ?int $updaterId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($rule, $data, $updaterId) {
            if (isset($data['code']) && $data['code'] !== $rule->code) {
                $this->ensureCodeUnique($data['code']);
            }

            $userLevel = $data['user_level'] ?? $rule->user_level;
            $currency = $data['currency'] ?? $rule->currency;
            $method = $data['withdrawal_method'] ?? $rule->withdrawal_method;
            $isActive = $data['is_active'] ?? $rule->is_active;

            if ($isActive && $this->hasDimensionChanged($rule, $userLevel, $currency, $method)) {
                $this->repository->deactivateConflictingRules(
                    $userLevel,
                    $currency,
                    $method,
                    $rule->id,
                    $updaterId
                );
            }

            $this->repository->update($rule, array_merge($data, [
                'updated_by' => $updaterId,
            ]));

            return $rule->fresh()->load(['creator', 'updater']);
        });
    }

    public function deleteRule(WithdrawalRule $rule): void
    {
        $withdrawalCount = $rule->withdrawals()->count();

        if ($withdrawalCount > 0) {
            throw WithdrawalRuleInUseException::hasWithdrawals($rule->id, $withdrawalCount);
        }

        $this->repository->delete($rule);
    }

    public function toggleActive(WithdrawalRule $rule, ?int $updaterId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($rule, $updaterId) {
            $newStatus = !$rule->is_active;

            if ($newStatus) {
                $this->repository->deactivateConflictingRules(
                    $rule->user_level,
                    $rule->currency,
                    $rule->withdrawal_method,
                    $rule->id,
                    $updaterId
                );
            }

            $this->repository->update($rule, [
                'is_active' => $newStatus,
                'updated_by' => $updaterId,
            ]);

            return $rule->fresh();
        });
    }

    public function calculateFee(float $amount, WithdrawalRule $rule): float
    {
        return $rule->calculateFee($amount);
    }

    public function validateAmount(float $amount, WithdrawalRule $rule): void
    {
        if (!$rule->isValidAmount($amount)) {
            if ($amount < $rule->min_amount) {
                throw InvalidWithdrawalAmountException::belowMinimum(
                    $amount,
                    $rule->min_amount,
                    $rule->currency
                );
            }

            throw InvalidWithdrawalAmountException::aboveMaximum(
                $amount,
                $rule->max_amount,
                $rule->currency
            );
        }
    }

    protected function ensureCodeUnique(?string $code, ?int $excludeId = null): void
    {
        if ($code === null) {
            return;
        }

        $query = $this->repository->newModel()->where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw WithdrawalRuleConflictException::codeExists($code);
        }
    }

    protected function hasDimensionChanged(
        WithdrawalRule $rule,
        string $userLevel,
        string $currency,
        string $method
    ): bool {
        return $userLevel !== $rule->user_level
            || $currency !== $rule->currency
            || $method !== $rule->withdrawal_method
            || !$rule->is_active;
    }
}
