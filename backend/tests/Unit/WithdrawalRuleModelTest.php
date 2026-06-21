<?php

namespace Tests\Unit;

use App\Models\Withdrawal;
use App\Models\WithdrawalRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalRuleModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_fee_with_rate_only(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.01,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $this->assertEquals(10.0, $rule->calculateFee(1000));
    }

    public function test_calculate_fee_with_fixed_fee_only(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0,
            'fixed_fee' => 5.50,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $this->assertEquals(5.50, $rule->calculateFee(1000));
    }

    public function test_calculate_fee_with_rate_and_fixed(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.01,
            'fixed_fee' => 2.00,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $this->assertEquals(12.0, $rule->calculateFee(1000));
    }

    public function test_calculate_fee_with_min_fee(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.001,
            'fixed_fee' => 0,
            'fee_min' => 5.00,
            'fee_max' => 0,
        ]);

        $this->assertEquals(5.0, $rule->calculateFee(100));
    }

    public function test_calculate_fee_with_max_fee(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.05,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 50.00,
        ]);

        $this->assertEquals(50.0, $rule->calculateFee(10000));
    }

    public function test_calculate_fee_within_min_max_range(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.01,
            'fixed_fee' => 0,
            'fee_min' => 2.00,
            'fee_max' => 100.00,
        ]);

        $this->assertEquals(10.0, $rule->calculateFee(1000));
    }

    public function test_calculate_fee_zero_when_no_fees(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $this->assertEquals(0.0, $rule->calculateFee(1000));
    }

    public function test_calculate_fee_rounds_to_two_decimals(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'fee_rate' => 0.0333,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);

        $fee = $rule->calculateFee(1000);

        $this->assertEquals(33.30, $fee);
    }

    public function test_is_applicable_returns_true_for_active_matching_rule(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'effective_from' => null,
            'effective_to' => null,
        ]);

        $this->assertTrue($rule->isApplicable(WithdrawalRule::LEVEL_VIP, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_for_inactive_rule(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => false,
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_VIP, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_when_user_level_mismatch(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_true_for_all_level(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
        ]);

        $this->assertTrue($rule->isApplicable(WithdrawalRule::LEVEL_VIP, 'CNY', 'bank_transfer'));
        $this->assertTrue($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
        $this->assertTrue($rule->isApplicable(WithdrawalRule::LEVEL_SUPER, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_when_currency_mismatch(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'USD',
            'withdrawal_method' => 'bank_transfer',
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_when_method_mismatch(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'alipay',
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_before_effective_from(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'effective_from' => now()->addWeek(),
            'effective_to' => null,
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_false_after_effective_to(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'effective_from' => null,
            'effective_to' => now()->subDay(),
        ]);

        $this->assertFalse($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_applicable_returns_true_within_effective_period(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'is_active' => true,
            'user_level' => WithdrawalRule::LEVEL_ALL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'effective_from' => now()->subWeek(),
            'effective_to' => now()->addWeek(),
        ]);

        $this->assertTrue($rule->isApplicable(WithdrawalRule::LEVEL_NORMAL, 'CNY', 'bank_transfer'));
    }

    public function test_is_valid_amount_returns_true_within_range(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->assertTrue($rule->isValidAmount(1000));
    }

    public function test_is_valid_amount_returns_false_below_minimum(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->assertFalse($rule->isValidAmount(50));
    }

    public function test_is_valid_amount_returns_false_above_maximum(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->assertFalse($rule->isValidAmount(60000));
    }

    public function test_is_valid_amount_returns_true_at_min_boundary(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->assertTrue($rule->isValidAmount(100));
    }

    public function test_is_valid_amount_returns_true_at_max_boundary(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 50000,
        ]);

        $this->assertTrue($rule->isValidAmount(50000));
    }

    public function test_is_valid_amount_zero_max_means_no_upper_limit(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'min_amount' => 100,
            'max_amount' => 0,
        ]);

        $this->assertTrue($rule->isValidAmount(999999));
    }

    public function test_requires_approval_returns_true_when_flag_enabled(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'require_approval' => true,
            'approval_threshold' => 0,
        ]);

        $this->assertTrue($rule->requiresApproval(100));
    }

    public function test_requires_approval_returns_true_when_above_threshold(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'require_approval' => false,
            'approval_threshold' => 50000,
        ]);

        $this->assertTrue($rule->requiresApproval(60000));
    }

    public function test_requires_approval_returns_false_when_below_threshold(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'require_approval' => false,
            'approval_threshold' => 50000,
        ]);

        $this->assertFalse($rule->requiresApproval(10000));
    }

    public function test_requires_approval_returns_true_at_threshold_boundary(): void
    {
        $rule = WithdrawalRule::factory()->make([
            'require_approval' => false,
            'approval_threshold' => 50000,
        ]);

        $this->assertTrue($rule->requiresApproval(50000));
    }

    public function test_scope_active_filters_active_rules(): void
    {
        WithdrawalRule::factory()->count(3)->create(['is_active' => true]);
        WithdrawalRule::factory()->count(2)->create(['is_active' => false]);

        $active = WithdrawalRule::active()->get();

        $this->assertCount(3, $active);
        $active->each(fn ($r) => $this->assertTrue($r->is_active));
    }

    public function test_scope_inactive_filters_inactive_rules(): void
    {
        WithdrawalRule::factory()->count(2)->create(['is_active' => true]);
        WithdrawalRule::factory()->count(3)->create(['is_active' => false]);

        $inactive = WithdrawalRule::inactive()->get();

        $this->assertCount(3, $inactive);
        $inactive->each(fn ($r) => $this->assertFalse($r->is_active));
    }

    public function test_scope_by_user_level_matches_exact_or_all(): void
    {
        WithdrawalRule::factory()->create(['user_level' => WithdrawalRule::LEVEL_VIP]);
        WithdrawalRule::factory()->create(['user_level' => WithdrawalRule::LEVEL_ALL]);
        WithdrawalRule::factory()->create(['user_level' => WithdrawalRule::LEVEL_NORMAL]);

        $result = WithdrawalRule::byUserLevel(WithdrawalRule::LEVEL_VIP)->get();

        $this->assertCount(2, $result);
        $levels = $result->pluck('user_level')->unique();
        $this->assertTrue($levels->contains(WithdrawalRule::LEVEL_VIP));
        $this->assertTrue($levels->contains(WithdrawalRule::LEVEL_ALL));
    }

    public function test_scope_by_currency_filters_by_currency(): void
    {
        WithdrawalRule::factory()->create(['currency' => 'CNY']);
        WithdrawalRule::factory()->create(['currency' => 'USD']);
        WithdrawalRule::factory()->create(['currency' => 'CNY']);

        $result = WithdrawalRule::byCurrency('USD')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('USD', $result->first()->currency);
    }

    public function test_scope_by_method_filters_by_method(): void
    {
        WithdrawalRule::factory()->create(['withdrawal_method' => 'bank_transfer']);
        WithdrawalRule::factory()->create(['withdrawal_method' => 'alipay']);

        $result = WithdrawalRule::byMethod('alipay')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('alipay', $result->first()->withdrawal_method);
    }

    public function test_scope_currently_effective_filters_by_date(): void
    {
        WithdrawalRule::factory()->create([
            'effective_from' => null,
            'effective_to' => null,
        ]);

        WithdrawalRule::factory()->create([
            'effective_from' => now()->subWeek(),
            'effective_to' => now()->addWeek(),
        ]);

        WithdrawalRule::factory()->create([
            'effective_from' => null,
            'effective_to' => now()->subDay(),
        ]);

        WithdrawalRule::factory()->create([
            'effective_from' => now()->addWeek(),
            'effective_to' => null,
        ]);

        $result = WithdrawalRule::currentlyEffective()->get();

        $this->assertCount(2, $result);
    }

    public function test_scope_keyword_searches_name_code_description(): void
    {
        WithdrawalRule::factory()->create([
            'name' => '普通用户银行转账',
            'code' => 'NORMAL_BANK_001',
            'description' => '适用于普通用户的银行转账规则',
        ]);

        WithdrawalRule::factory()->create([
            'name' => 'VIP支付宝规则',
            'code' => 'VIP_ALIPAY_001',
            'description' => 'VIP用户专属支付宝提现',
        ]);

        $result = WithdrawalRule::keyword('银行')->get();

        $this->assertCount(1, $result);
        $this->assertEquals('NORMAL_BANK_001', $result->first()->code);
    }

    public function test_scope_ordered_sorts_by_sort_order_and_id(): void
    {
        $r1 = WithdrawalRule::factory()->create(['sort_order' => 10]);
        $r2 = WithdrawalRule::factory()->create(['sort_order' => 1]);
        $r3 = WithdrawalRule::factory()->create(['sort_order' => 5]);

        $result = WithdrawalRule::ordered()->get();

        $this->assertEquals($r2->id, $result[0]->id);
        $this->assertEquals($r3->id, $result[1]->id);
        $this->assertEquals($r1->id, $result[2]->id);
    }

    public function test_withdrawals_relationship_returns_associated_withdrawals(): void
    {
        $rule = WithdrawalRule::factory()->create();
        Withdrawal::factory()->count(3)->forRule($rule)->create();
        Withdrawal::factory()->count(2)->create();

        $this->assertCount(3, $rule->withdrawals);
    }

    public function test_get_status_options_returns_correct_labels(): void
    {
        $options = WithdrawalRule::getStatusOptions();

        $this->assertCount(2, $options);
        $this->assertEquals(true, $options[0]['value']);
        $this->assertEquals('启用', $options[0]['label']);
    }

    public function test_get_level_options_returns_all_levels(): void
    {
        $options = WithdrawalRule::getLevelOptions();

        $this->assertCount(4, $options);
        $values = collect($options)->pluck('value');
        $this->assertTrue($values->contains(WithdrawalRule::LEVEL_ALL));
        $this->assertTrue($values->contains(WithdrawalRule::LEVEL_SUPER));
        $this->assertTrue($values->contains(WithdrawalRule::LEVEL_VIP));
        $this->assertTrue($values->contains(WithdrawalRule::LEVEL_NORMAL));
    }

    public function test_fillable_attributes(): void
    {
        $rule = new WithdrawalRule();
        $fillable = $rule->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('min_amount', $fillable);
        $this->assertContains('max_amount', $fillable);
        $this->assertContains('fee_rate', $fillable);
        $this->assertContains('is_active', $fillable);
    }

    public function test_casts_attributes(): void
    {
        $rule = WithdrawalRule::factory()->create([
            'is_active' => true,
            'require_approval' => true,
            'min_amount' => '100.50',
            'allowed_regions' => ['CN', 'US'],
        ]);

        $rule->refresh();

        $this->assertIsBool($rule->is_active);
        $this->assertIsBool($rule->require_approval);
        $this->assertIsArray($rule->allowed_regions);
    }
}
