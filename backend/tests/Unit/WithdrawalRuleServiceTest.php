<?php

namespace Tests\Unit;

use App\Enums\UserType;
use App\Exceptions\WithdrawalRule\InvalidWithdrawalAmountException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleConflictException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleInUseException;
use App\Exceptions\WithdrawalRule\WithdrawalRuleNotFoundException;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalRule;
use App\Repositories\WithdrawalRuleRepository;
use App\Services\WithdrawalRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WithdrawalRuleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WithdrawalRuleService::class);
    }

    public function test_get_rule_list_returns_paginated_results(): void
    {
        WithdrawalRule::factory()->count(25)->create();

        $result = $this->service->getRuleList(['page' => 1, 'per_page' => 10]);

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(1, $result->currentPage());
    }

    public function test_get_rule_list_with_keyword_filter(): void
    {
        WithdrawalRule::factory()->create(['name' => 'VIP用户银行转账规则', 'code' => 'VIP_BANK_001']);
        WithdrawalRule::factory()->count(3)->create();

        $result = $this->service->getRuleList(['keyword' => 'VIP']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('VIP_BANK_001', $result->first()->code);
    }

    public function test_get_rule_list_with_user_level_filter(): void
    {
        WithdrawalRule::factory()->create(['user_level' => WithdrawalRule::LEVEL_VIP]);
        WithdrawalRule::factory()->create(['user_level' => WithdrawalRule::LEVEL_NORMAL]);
        WithdrawalRule::factory()->count(2)->create();

        $result = $this->service->getRuleList(['user_level' => WithdrawalRule::LEVEL_VIP]);

        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    public function test_get_rule_list_with_currency_filter(): void
    {
        WithdrawalRule::factory()->create(['currency' => 'USD']);
        WithdrawalRule::factory()->create(['currency' => 'CNY']);

        $result = $this->service->getRuleList(['currency' => 'USD']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('USD', $result->first()->currency);
    }

    public function test_get_rule_list_with_method_filter(): void
    {
        WithdrawalRule::factory()->create(['withdrawal_method' => 'alipay']);
        WithdrawalRule::factory()->create(['withdrawal_method' => 'bank_transfer']);

        $result = $this->service->getRuleList(['withdrawal_method' => 'alipay']);

        $this->assertEquals(1, $result->total());
        $this->assertEquals('alipay', $result->first()->withdrawal_method);
    }

    public function test_get_rule_list_with_active_filter(): void
    {
        WithdrawalRule::factory()->create(['is_active' => true]);
        WithdrawalRule::factory()->create(['is_active' => false]);

        $result = $this->service->getRuleList(['is_active' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total());
        $this->assertTrue($result->first()->is_active);
    }

    public function test_get_current_rule_returns_applicable_rule(): void
    {
        $rule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $result = $this->service->getCurrentRule(
            WithdrawalRule::LEVEL_VIP,
            'CNY',
            'bank_transfer'
        );

        $this->assertNotNull($result);
        $this->assertEquals($rule->id, $result->id);
    }

    public function test_get_current_rule_returns_null_when_no_match(): void
    {
        $result = $this->service->getCurrentRule('vip', 'CNY', 'bank_transfer');

        $this->assertNull($result);
    }

    public function test_get_current_rule_matches_all_level(): void
    {
        WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $result = $this->service->getCurrentRule(
            WithdrawalRule::LEVEL_VIP,
            'CNY',
            'bank_transfer'
        );

        $this->assertNotNull($result);
        $this->assertEquals(WithdrawalRule::LEVEL_ALL, $result->user_level);
    }

    public function test_get_current_rule_or_fail_throws_exception_when_not_found(): void
    {
        $this->expectException(WithdrawalRuleNotFoundException::class);

        $this->service->getCurrentRuleOrFail('vip', 'CNY', 'bank_transfer');
    }

    public function test_get_current_rule_or_fail_returns_rule_when_found(): void
    {
        $rule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $result = $this->service->getCurrentRuleOrFail('normal', 'CNY', 'bank_transfer');

        $this->assertEquals($rule->id, $result->id);
    }

    public function test_get_rule_by_id_returns_rule_with_relations(): void
    {
        $user = User::factory()->create();
        $rule = WithdrawalRule::factory()->create(['created_by' => $user->id, 'updated_by' => $user->id]);

        $result = $this->service->getRuleById($rule->id);

        $this->assertEquals($rule->id, $result->id);
        $this->assertTrue($result->relationLoaded('creator'));
        $this->assertTrue($result->relationLoaded('updater'));
        $this->assertNotNull($result->withdrawals_count);
    }

    public function test_get_rule_by_id_throws_exception_when_not_found(): void
    {
        $this->expectException(WithdrawalRuleNotFoundException::class);

        $this->service->getRuleById(99999);
    }

    public function test_get_active_rules_returns_only_active_and_effective(): void
    {
        WithdrawalRule::factory()->create(['is_active' => true]);
        WithdrawalRule::factory()->create(['is_active' => false]);
        WithdrawalRule::factory()->create([
            'is_active' => true,
            'effective_to' => now()->subDay(),
        ]);

        $result = $this->service->getActiveRules();

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->is_active);
    }

    public function test_create_rule_successfully(): void
    {
        $user = User::factory()->create();
        $data = [
            'name' => '测试规则',
            'code' => 'TEST_RULE_001',
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'min_amount' => 100,
            'max_amount' => 50000,
            'fee_rate' => 0.005,
            'is_active' => true,
        ];

        $rule = $this->service->createRule($data, $user->id);

        $this->assertInstanceOf(WithdrawalRule::class, $rule);
        $this->assertEquals('TEST_RULE_001', $rule->code);
        $this->assertEquals($user->id, $rule->created_by);
        $this->assertEquals($user->id, $rule->updated_by);
    }

    public function test_create_rule_throws_exception_on_duplicate_code(): void
    {
        $this->expectException(WithdrawalRuleConflictException::class);

        WithdrawalRule::factory()->create(['code' => 'DUPLICATE_CODE']);

        $this->service->createRule([
            'name' => '测试规则',
            'code' => 'DUPLICATE_CODE',
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
        ]);
    }

    public function test_create_active_rule_deactivates_conflicting_rules(): void
    {
        $user = User::factory()->create();

        $existingRule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $newRule = $this->service->createRule([
            'name' => '新VIP规则',
            'code' => 'NEW_VIP_RULE',
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'min_amount' => 100,
            'max_amount' => 100000,
            'fee_rate' => 0.003,
            'is_active' => true,
        ], $user->id);

        $existingRule->refresh();
        $this->assertFalse($existingRule->is_active);
        $this->assertTrue($newRule->is_active);
    }

    public function test_create_inactive_rule_does_not_deactivate_conflicts(): void
    {
        $existingRule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->service->createRule([
            'name' => '新VIP规则',
            'code' => 'NEW_VIP_RULE',
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => false,
        ]);

        $existingRule->refresh();
        $this->assertTrue($existingRule->is_active);
    }

    public function test_update_rule_successfully(): void
    {
        $user = User::factory()->create();
        $rule = WithdrawalRule::factory()->create(['name' => '旧名称', 'code' => 'OLD_CODE']);

        $updated = $this->service->updateRule($rule, [
            'name' => '新名称',
            'min_amount' => 200,
        ], $user->id);

        $this->assertEquals('新名称', $updated->name);
        $this->assertEquals(200, $updated->min_amount);
        $this->assertEquals($user->id, $updated->updated_by);
    }

    public function test_update_rule_changing_code_throws_on_duplicate(): void
    {
        $this->expectException(WithdrawalRuleConflictException::class);

        WithdrawalRule::factory()->create(['code' => 'EXISTING_CODE']);
        $rule = WithdrawalRule::factory()->create(['code' => 'ORIGINAL_CODE']);

        $this->service->updateRule($rule, ['code' => 'EXISTING_CODE']);
    }

    public function test_update_rule_same_code_does_not_throw(): void
    {
        $rule = WithdrawalRule::factory()->create(['code' => 'SAME_CODE', 'name' => '旧名称']);

        $updated = $this->service->updateRule($rule, [
            'code' => 'SAME_CODE',
            'name' => '新名称',
        ]);

        $this->assertEquals('新名称', $updated->name);
        $this->assertEquals('SAME_CODE', $updated->code);
    }

    public function test_update_rule_with_dimension_change_deactivates_conflicts(): void
    {
        $user = User::factory()->create();
        $rule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $conflictingRule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->service->updateRule($rule, [
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'is_active' => true,
        ], $user->id);

        $conflictingRule->refresh();
        $this->assertFalse($conflictingRule->is_active);
    }

    public function test_delete_rule_successfully_when_no_withdrawals(): void
    {
        $rule = WithdrawalRule::factory()->create();

        $this->service->deleteRule($rule);

        $this->assertSoftDeleted($rule);
    }

    public function test_delete_rule_throws_exception_when_has_withdrawals(): void
    {
        $this->expectException(WithdrawalRuleInUseException::class);

        $rule = WithdrawalRule::factory()->create();
        Withdrawal::factory()->count(3)->forRule($rule)->create();

        $this->service->deleteRule($rule);
    }

    public function test_toggle_active_from_inactive_to_active(): void
    {
        $user = User::factory()->create();
        $rule = WithdrawalRule::factory()->create(['is_active' => false]);

        $updated = $this->service->toggleActive($rule, $user->id);

        $this->assertTrue($updated->is_active);
        $this->assertEquals($user->id, $updated->updated_by);
    }

    public function test_toggle_active_from_active_to_inactive(): void
    {
        $rule = WithdrawalRule::factory()->create(['is_active' => true]);

        $updated = $this->service->toggleActive($rule);

        $this->assertFalse($updated->is_active);
    }

    public function test_toggle_active_activating_deactivates_conflicts(): void
    {
        $user = User::factory()->create();
        $rule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => false,
        ]);

        $conflictingRule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->service->toggleActive($rule, $user->id);

        $conflictingRule->refresh();
        $this->assertFalse($conflictingRule->is_active);
    }

    public function test_calculate_fee_basic_rate(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.01,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $fee = $this->service->calculateFee(1000, $rule);

        $this->assertEquals(10.0, $fee);
    }

    public function test_calculate_fee_with_fixed_fee(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0,
            'fixed_fee' => 5.00,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $fee = $this->service->calculateFee(1000, $rule);

        $this->assertEquals(5.0, $fee);
    }

    public function test_calculate_fee_with_min_fee(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.001,
            'fixed_fee' => 0,
            'fee_min' => 5.00,
            'fee_max' => 0,
        ]);

        $fee = $this->service->calculateFee(100, $rule);

        $this->assertEquals(5.0, $fee);
    }

    public function test_calculate_fee_with_max_fee(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.05,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 50.00,
        ]);

        $fee = $this->service->calculateFee(10000, $rule);

        $this->assertEquals(50.0, $fee);
    }

    public function test_validate_amount_passes_when_valid(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->service->validateAmount(1000, $rule);

        $this->assertTrue(true);
    }

    public function test_validate_amount_throws_below_minimum(): void
    {
        $this->expectException(InvalidWithdrawalAmountException::class);

        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
            'currency' => 'CNY',
        ]);

        $this->service->validateAmount(50, $rule);
    }

    public function test_validate_amount_throws_above_maximum(): void
    {
        $this->expectException(InvalidWithdrawalAmountException::class);

        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
            'currency' => 'CNY',
        ]);

        $this->service->validateAmount(60000, $rule);
    }

    public function test_validate_amount_zero_max_means_no_upper_limit(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 0,
        ]);

        $this->service->validateAmount(999999, $rule);

        $this->assertTrue(true);
    }

    public function test_validate_amount_at_boundary_min(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->service->validateAmount(100, $rule);

        $this->assertTrue(true);
    }

    public function test_validate_amount_at_boundary_max(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->service->validateAmount(50000, $rule);

        $this->assertTrue(true);
    }
}
