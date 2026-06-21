<?php

namespace App\Services;

use App\Models\WithdrawalRule;
use Illuminate\Support\Facades\DB;

class WithdrawalRuleService
{
    public function getRuleList(array $params = [])
    {
        $query = WithdrawalRule::with(['creator', 'updater'])
            ->when(!empty($params['keyword']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('name', 'like', "%{$params['keyword']}%")
                        ->orWhere('code', 'like', "%{$params['keyword']}%")
                        ->orWhere('description', 'like', "%{$params['keyword']}%");
                });
            })
            ->when(!empty($params['user_level']), function ($q) use ($params) {
                $q->where(function ($query) use ($params) {
                    $query->where('user_level', WithdrawalRule::LEVEL_ALL)
                        ->orWhere('user_level', $params['user_level']);
                });
            })
            ->when(!empty($params['currency']), function ($q) use ($params) {
                $q->where('currency', $params['currency']);
            })
            ->when(!empty($params['withdrawal_method']), function ($q) use ($params) {
                $q->where('withdrawal_method', $params['withdrawal_method']);
            })
            ->when(isset($params['is_active']) && $params['is_active'] !== '', function ($q) use ($params) {
                $q->where('is_active', $params['is_active']);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getCurrentRule(string $userLevel = 'normal', string $currency = 'CNY', string $method = 'bank_transfer'): ?WithdrawalRule
    {
        return WithdrawalRule::where('is_active', true)
            ->where(function ($query) use ($userLevel) {
                $query->where('user_level', WithdrawalRule::LEVEL_ALL)
                    ->orWhere('user_level', $userLevel);
            })
            ->where('currency', $currency)
            ->where('withdrawal_method', $method)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('fee_rate', 'asc')
            ->first();
    }

    public function createRule(array $data, ?int $creatorId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($data, $creatorId) {
            if (!empty($data['is_active'])) {
                $this->deactivateConflictingRules(
                    $data['user_level'] ?? WithdrawalRule::LEVEL_NORMAL,
                    $data['currency'] ?? 'CNY',
                    $data['withdrawal_method'] ?? 'bank_transfer'
                );
            }

            $rule = WithdrawalRule::create(array_merge($data, [
                'created_by' => $creatorId,
                'updated_by' => $creatorId,
            ]));

            return $rule->fresh()->load(['creator', 'updater']);
        });
    }

    public function updateRule(WithdrawalRule $rule, array $data, ?int $updaterId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($rule, $data, $updaterId) {
            $userLevel = $data['user_level'] ?? $rule->user_level;
            $currency = $data['currency'] ?? $rule->currency;
            $method = $data['withdrawal_method'] ?? $rule->withdrawal_method;
            $isActive = $data['is_active'] ?? $rule->is_active;

            if ($isActive && (
                $userLevel !== $rule->user_level ||
                $currency !== $rule->currency ||
                $method !== $rule->withdrawal_method ||
                !$rule->is_active
            )) {
                $this->deactivateConflictingRules($userLevel, $currency, $method, $rule->id);
            }

            $rule->update(array_merge($data, [
                'updated_by' => $updaterId,
            ]));

            return $rule->fresh()->load(['creator', 'updater']);
        });
    }

    public function deleteRule(WithdrawalRule $rule): void
    {
        if ($rule->withdrawals()->exists()) {
            throw new \App\Exceptions\Withdrawal\WithdrawalException(
                '该规则下存在提现记录，无法删除'
            );
        }

        $rule->delete();
    }

    public function toggleActive(WithdrawalRule $rule, ?int $updaterId = null): WithdrawalRule
    {
        return DB::transaction(function () use ($rule, $updaterId) {
            $newStatus = !$rule->is_active;

            if ($newStatus) {
                $this->deactivateConflictingRules(
                    $rule->user_level,
                    $rule->currency,
                    $rule->withdrawal_method,
                    $rule->id
                );
            }

            $rule->update([
                'is_active' => $newStatus,
                'updated_by' => $updaterId,
            ]);

            return $rule->fresh();
        });
    }

    protected function deactivateConflictingRules(string $userLevel, string $currency, string $method, ?int $excludeId = null): void
    {
        $query = WithdrawalRule::where('is_active', true)
            ->where(function ($q) use ($userLevel) {
                $q->where('user_level', WithdrawalRule::LEVEL_ALL)
                    ->orWhere('user_level', $userLevel);
            })
            ->where('currency', $currency)
            ->where('withdrawal_method', $method)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->update([
            'is_active' => false,
            'updated_by' => auth()->id() ?? null,
        ]);
    }
}
