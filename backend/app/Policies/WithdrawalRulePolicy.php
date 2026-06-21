<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WithdrawalRule;
use App\Services\PermissionService;
use Illuminate\Auth\Access\Response;

class WithdrawalRulePolicy
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view-withdrawal-rules')
            || $user->can('withdraw-rule.view')
            || $user->isPlatform();
    }

    public function view(User $user, WithdrawalRule $rule): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-withdrawal-rules')
            || $user->can('withdraw-rule.manage')
            || $user->isPlatform();
    }

    public function update(User $user, WithdrawalRule $rule): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, WithdrawalRule $rule): bool
    {
        return $this->create($user);
    }

    public function toggleActive(User $user, WithdrawalRule $rule): Response
    {
        if (!$this->create($user)) {
            return Response::deny('您无权启用/禁用提现规则');
        }

        return Response::allow();
    }

    public function viewOptions(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function viewCurrent(User $user): bool
    {
        return true;
    }
}
