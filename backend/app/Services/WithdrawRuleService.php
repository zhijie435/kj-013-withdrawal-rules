<?php

namespace App\Services;

use App\Enums\UserType;
use App\Exceptions\BusinessException;
use App\Models\Distributor;
use App\Models\ShearerlineConfig;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WithdrawRuleService
{
    public function getRules(array $params = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = WithdrawRule::query()->with(['method']);

        if (isset($params['user_level'])) {
            $query->where('user_level', $params['user_level']);
        }

        if (isset($params['withdraw_method_id'])) {
            $query->where('withdraw_method_id', $params['withdraw_method_id']);
        }

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        $pagination = $query->orderBy('id', 'desc')->paginate($params['per_page'] ?? 20);

        $pagination->getCollection()->transform(function (WithdrawRule $rule) {
            $rule->setAttribute('globally_enabled', WithdrawRule::isGloballyEnabled());
            $rule->setAttribute('is_effectively_enabled', $rule->isEnabled());
            return $rule;
        });

        return $pagination;
    }

    public function getEnabledRules(?string $userLevel = null): \Illuminate\Database\Eloquent\Collection
    {
        if (!WithdrawRule::isGloballyEnabled()) {
            return collect();
        }

        return WithdrawRule::enabled()
            ->when($userLevel, fn (Builder $q) => $q->where('user_level', $userLevel))
            ->with(['method'])
            ->get()
            ->filter(fn (WithdrawRule $r) => $r->isEnabled())
            ->values();
    }

    public function getRule(int $id): WithdrawRule
    {
        return WithdrawRule::with(['method'])->findOrFail($id);
    }

    public function createRule(array $data, User $operator): WithdrawRule
    {
        $exists = WithdrawRule::where('user_level', $data['user_level'])
            ->where('withdraw_method_id', $data['withdraw_method_id'])
            ->exists();

        if ($exists) {
            throw BusinessException::withCode('该用户等级和提现方式的规则已存在', 'WITHDRAW_RULE_EXISTS');
        }

        return DB::transaction(function () use ($data) {
            if (!empty($data['status'])) {
                $this->disableConflictingRules($data['user_level'], $data['withdraw_method_id']);
            }

            return WithdrawRule::create($data);
        });
    }

    public function updateRule(WithdrawRule $rule, array $data, User $operator): WithdrawRule
    {
        if (
            ($data['user_level'] ?? $rule->user_level) !== $rule->user_level ||
            ($data['withdraw_method_id'] ?? $rule->withdraw_method_id) !== $rule->withdraw_method_id
        ) {
            $exists = WithdrawRule::where('user_level', $data['user_level'] ?? $rule->user_level)
                ->where('withdraw_method_id', $data['withdraw_method_id'] ?? $rule->withdraw_method_id)
                ->where('id', '!=', $rule->id)
                ->exists();

            if ($exists) {
                throw BusinessException::withCode('该用户等级和提现方式的规则已存在', 'WITHDRAW_RULE_EXISTS');
            }
        }

        return DB::transaction(function () use ($rule, $data) {
            if (!empty($data['status'])) {
                $this->disableConflictingRules(
                    $data['user_level'] ?? $rule->user_level,
                    $data['withdraw_method_id'] ?? $rule->withdraw_method_id,
                    $rule->id
                );
            }

            $rule->update($data);

            return $rule->refresh();
        });
    }

    public function deleteRule(WithdrawRule $rule, User $operator): void
    {
        $hasUsed = $rule->withdrawRequests()->exists();

        if ($hasUsed) {
            throw BusinessException::withCode('该规则已被使用，无法删除', 'WITHDRAW_RULE_USED');
        }

        $rule->delete();
    }

    public function toggleRuleStatus(WithdrawRule $rule, bool $status, User $operator): WithdrawRule
    {
        return DB::transaction(function () use ($rule, $status) {
            if ($status) {
                $this->disableConflictingRules($rule->user_level, $rule->withdraw_method_id, $rule->id);
            }

            $rule->status = $status;
            $rule->save();

            return $rule;
        });
    }

    protected function disableConflictingRules(string $userLevel, int $methodId, ?int $excludeId = null): void
    {
        WithdrawRule::where('user_level', $userLevel)
            ->where('withdraw_method_id', $methodId)
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->update(['status' => false]);
    }

    public function getApplicableRule(User $user, int $methodId): ?WithdrawRule
    {
        $userLevel = $this->getUserLevel($user);

        return WithdrawRule::getRule($userLevel, $methodId);
    }

    protected function getUserLevel(User $user): string
    {
        if ($user->isDistributor() && $user->distributor) {
            return $user->distributor->level ?? 'normal';
        }

        return match ($user->user_type) {
            UserType::PLATFORM => 'admin',
            UserType::SUPPLIER => 'supplier',
            default => 'normal',
        };
    }

    public function validateWithdrawAmount(User $user, int $methodId, float $amount): array
    {
        if (!WithdrawRule::isGloballyEnabled()) {
            throw BusinessException::withCode('提现功能已临时关闭，请稍后再试', 'WITHDRAW_GLOBALLY_DISABLED');
        }

        $rule = $this->getApplicableRule($user, $methodId);

        if (!$rule) {
            throw BusinessException::withCode('未找到适用的提现规则', 'WITHDRAW_RULE_NOT_FOUND');
        }

        if (!$rule->isEnabled()) {
            if (!WithdrawRule::isGloballyEnabled()) {
                throw BusinessException::withCode('提现功能已临时关闭，请稍后再试', 'WITHDRAW_GLOBALLY_DISABLED');
            }
            if (!$rule->method?->isEnabled()) {
                throw BusinessException::withCode('该提现方式暂未开放', 'WITHDRAW_METHOD_DISABLED');
            }
            throw BusinessException::withCode('该提现规则暂未启用', 'WITHDRAW_RULE_DISABLED');
        }

        if (!$rule->isValidAmount($amount)) {
            throw BusinessException::withCode(
                "提现金额必须在 ¥{$rule->min_amount} - ¥{$rule->max_amount} 之间",
                'WITHDRAW_AMOUNT_INVALID',
                ['min' => $rule->min_amount, 'max' => $rule->max_amount]
            );
        }

        $todayCount = $user->withdrawRequests()
            ->today()
            ->where('withdraw_method_id', $methodId)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->count();

        if ($rule->daily_max_count > 0 && $todayCount >= $rule->daily_max_count) {
            throw BusinessException::withCode(
                '今日提现次数已达上限',
                'WITHDRAW_DAILY_COUNT_EXCEEDED',
                ['max_count' => $rule->daily_max_count]
            );
        }

        $todayAmount = $user->withdrawRequests()
            ->today()
            ->where('withdraw_method_id', $methodId)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->sum('amount');

        if ($rule->daily_max_amount > 0 && ($todayAmount + $amount) > $rule->daily_max_amount) {
            throw BusinessException::withCode(
                '今日提现金额已达上限',
                'WITHDRAW_DAILY_AMOUNT_EXCEEDED',
                ['max_amount' => $rule->daily_max_amount, 'today_amount' => $todayAmount]
            );
        }

        $monthAmount = $user->withdrawRequests()
            ->thisMonth()
            ->where('withdraw_method_id', $methodId)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->sum('amount');

        if ($rule->monthly_max_amount > 0 && ($monthAmount + $amount) > $rule->monthly_max_amount) {
            throw BusinessException::withCode(
                '本月提现金额已达上限',
                'WITHDRAW_MONTHLY_AMOUNT_EXCEEDED',
                ['max_amount' => $rule->monthly_max_amount, 'month_amount' => $monthAmount]
            );
        }

        $monthCount = $user->withdrawRequests()
            ->thisMonth()
            ->where('withdraw_method_id', $methodId)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->count();

        if ($rule->monthly_max_count > 0 && $monthCount >= $rule->monthly_max_count) {
            throw BusinessException::withCode(
                '本月提现次数已达上限',
                'WITHDRAW_MONTHLY_COUNT_EXCEEDED',
                ['max_count' => $rule->monthly_max_count]
            );
        }

        $fee = $rule->calculateFee($amount);

        return [
            'valid' => true,
            'rule' => $rule,
            'fee' => $fee,
            'actual_amount' => round($amount - $fee, 2),
            'requires_audit' => $rule->requires_audit,
            'processing_days' => $rule->processing_days,
        ];
    }

    public function getMethods(): \Illuminate\Database\Eloquent\Collection
    {
        return WithdrawMethod::enabled()->ordered()->get();
    }
}
