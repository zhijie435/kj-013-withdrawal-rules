<?php

namespace App\Repositories;

use App\Models\WithdrawalRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class WithdrawalRuleRepository extends BaseRepository
{
    protected string $modelClass = WithdrawalRule::class;

    protected array $searchColumns = ['name', 'code', 'description'];

    protected array $filterColumns = [
        'user_level' => ['column' => 'user_level', 'type' => 'string'],
        'currency' => ['column' => 'currency', 'type' => 'string'],
        'withdrawal_method' => ['column' => 'withdrawal_method', 'type' => 'string'],
        'is_active' => ['column' => 'is_active', 'type' => 'bool'],
    ];

    protected string $defaultSortColumn = 'sort_order';

    protected string $defaultSortDirection = 'asc';

    public function findByCode(string $code): ?WithdrawalRule
    {
        return $this->newModel()->where('code', $code)->first();
    }

    public function getApplicableRule(
        string $userLevel,
        string $currency,
        string $method
    ): ?WithdrawalRule {
        return $this->newModel()
            ->active()
            ->byUserLevel($userLevel)
            ->byCurrency($currency)
            ->byMethod($method)
            ->currentlyEffective()
            ->ordered()
            ->orderBy('fee_rate', 'asc')
            ->first();
    }

    public function getActiveRules(): Collection
    {
        return $this->newModel()
            ->active()
            ->currentlyEffective()
            ->ordered()
            ->get();
    }

    public function getRulesByUserLevel(string $userLevel): Collection
    {
        return $this->newModel()
            ->active()
            ->byUserLevel($userLevel)
            ->currentlyEffective()
            ->ordered()
            ->get();
    }

    public function listPaginated(array $params = [], array $with = ['creator', 'updater']): LengthAwarePaginator
    {
        $query = $this->newModel()->with($with);

        if (!empty($params['keyword'])) {
            $query->keyword($params['keyword']);
        }

        if (!empty($params['user_level'])) {
            $query->byUserLevel($params['user_level']);
        }

        if (!empty($params['currency'])) {
            $query->byCurrency($params['currency']);
        }

        if (!empty($params['withdrawal_method'])) {
            $query->byMethod($params['withdrawal_method']);
        }

        if (isset($params['is_active']) && $params['is_active'] !== '') {
            $query->where('is_active', $params['is_active']);
        }

        $query->ordered();

        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 15;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function deactivateConflictingRules(
        string $userLevel,
        string $currency,
        string $method,
        ?int $excludeId = null,
        ?int $updatedBy = null
    ): int {
        $query = $this->newModel()
            ->active()
            ->byUserLevel($userLevel)
            ->byCurrency($currency)
            ->byMethod($method)
            ->currentlyEffective();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->update([
            'is_active' => false,
            'updated_by' => $updatedBy,
        ]);
    }

    public function create(array $data): WithdrawalRule
    {
        return $this->newModel()->create($data);
    }

    public function update(WithdrawalRule $rule, array $data): bool
    {
        return $rule->update($data);
    }

    public function delete(WithdrawalRule $rule): ?bool
    {
        return $rule->delete();
    }

    public function toggleActive(WithdrawalRule $rule, ?int $updatedBy = null): bool
    {
        return $rule->update([
            'is_active' => !$rule->is_active,
            'updated_by' => $updatedBy,
        ]);
    }
}
