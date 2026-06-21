<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WithdrawalRuleApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function createPlatformUser(): User
    {
        return User::factory()->create([
            'user_type' => UserType::PLATFORM,
        ]);
    }

    protected function actingAsPlatform(): User
    {
        $user = $this->createPlatformUser();
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_guest_cannot_access_withdrawal_rules(): void
    {
        $this->getJson('/api/withdrawal-rules')->assertStatus(401);
    }

    public function test_index_returns_paginated_rules(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->count(20)->create();

        $response = $this->getJson('/api/withdrawal-rules?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertEquals(20, $response->json('pagination.total'));
        $this->assertEquals(10, $response->json('pagination.per_page'));
    }

    public function test_index_with_keyword_filter(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->create([
            'name' => 'VIP用户专属银行转账规则',
            'code' => 'VIP_BANK_001',
        ]);
        WithdrawalRule::factory()->count(3)->create();

        $response = $this->getJson('/api/withdrawal-rules?keyword=VIP');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    public function test_index_with_user_level_filter(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->vip()->create();
        WithdrawalRule::factory()->normal()->create();

        $response = $this->getJson('/api/withdrawal-rules?user_level=' . WithdrawalRule::LEVEL_VIP);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    public function test_index_with_currency_filter(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->usd()->create();
        WithdrawalRule::factory()->cny()->count(2)->create();

        $response = $this->getJson('/api/withdrawal-rules?currency=USD');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('pagination.total'));
    }

    public function test_index_with_active_filter(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->active()->create();
        WithdrawalRule::factory()->inactive()->create();

        $response = $this->getJson('/api/withdrawal-rules?is_active=1');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('pagination.total'));
    }

    public function test_current_returns_applicable_rule(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()
            ->active()
            ->normal()
            ->cny()
            ->bankTransfer()
            ->create();

        $response = $this->getJson('/api/withdrawal-rules/current?user_level=normal&currency=CNY&withdrawal_method=bank_transfer');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        $this->assertEquals($rule->id, $response->json('data.id'));
    }

    public function test_current_returns_404_when_no_rule_found(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/current?user_level=vip&currency=USD&withdrawal_method=usdt');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error_code' => 'WITHDRAWAL_RULE_NOT_FOUND',
            ]);
    }

    public function test_get_status_options(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/status-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ]);
    }

    public function test_get_level_options(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/level-options');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_get_method_options(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/method-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ]);
    }

    public function test_get_currency_options(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/currency-options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ]);
    }

    public function test_store_creates_new_rule(): void
    {
        $user = $this->actingAsPlatform();

        $data = [
            'name' => '测试API创建规则',
            'code' => 'API_TEST_001',
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'min_amount' => 100,
            'max_amount' => 50000,
            'fee_rate' => 0.005,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/withdrawal-rules', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => '提现规则创建成功',
            ]);

        $this->assertDatabaseHas('withdrawal_rules', [
            'code' => 'API_TEST_001',
            'name' => '测试API创建规则',
            'created_by' => $user->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsPlatform();

        $response = $this->postJson('/api/withdrawal-rules', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_store_validates_unique_code(): void
    {
        $this->actingAsPlatform();
        WithdrawalRule::factory()->create(['code' => 'DUPLICATE']);

        $response = $this->postJson('/api/withdrawal-rules', [
            'name' => '测试',
            'code' => 'DUPLICATE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_validates_fee_rate_range(): void
    {
        $this->actingAsPlatform();

        $response = $this->postJson('/api/withdrawal-rules', [
            'name' => '测试',
            'code' => 'FEE_TEST',
            'fee_rate' => 1.5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fee_rate']);
    }

    public function test_store_validates_effective_to_after_effective_from(): void
    {
        $this->actingAsPlatform();

        $response = $this->postJson('/api/withdrawal-rules', [
            'name' => '测试',
            'code' => 'DATE_TEST',
            'effective_from' => '2026-12-31',
            'effective_to' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['effective_to']);
    }

    public function test_show_returns_rule_detail(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create();

        $response = $this->getJson("/api/withdrawal-rules/{$rule->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $rule->id,
                    'code' => $rule->code,
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_rule(): void
    {
        $this->actingAsPlatform();

        $response = $this->getJson('/api/withdrawal-rules/99999');

        $response->assertStatus(404);
    }

    public function test_update_modifies_existing_rule(): void
    {
        $user = $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create([
            'name' => '旧名称',
            'min_amount' => 100,
        ]);

        $response = $this->putJson("/api/withdrawal-rules/{$rule->id}", [
            'name' => '新名称',
            'min_amount' => 200,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '提现规则更新成功',
            ]);

        $rule->refresh();
        $this->assertEquals('新名称', $rule->name);
        $this->assertEquals(200, $rule->min_amount);
        $this->assertEquals($user->id, $rule->updated_by);
    }

    public function test_update_validates_unique_code_except_self(): void
    {
        $this->actingAsPlatform();
        $rule1 = WithdrawalRule::factory()->create(['code' => 'RULE_001']);
        WithdrawalRule::factory()->create(['code' => 'RULE_002']);

        $response = $this->putJson("/api/withdrawal-rules/{$rule1->id}", [
            'code' => 'RULE_001',
            'name' => '保持原编码',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_returns_404_for_nonexistent_rule(): void
    {
        $this->actingAsPlatform();

        $response = $this->putJson('/api/withdrawal-rules/99999', [
            'name' => '不存在',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_rule_without_withdrawals(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create();

        $response = $this->deleteJson("/api/withdrawal-rules/{$rule->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '提现规则删除成功',
            ]);

        $this->assertSoftDeleted($rule);
    }

    public function test_destroy_returns_error_when_rule_has_withdrawals(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create();
        Withdrawal::factory()->count(2)->forRule($rule)->create();

        $response = $this->deleteJson("/api/withdrawal-rules/{$rule->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'WITHDRAWAL_RULE_IN_USE',
            ]);

        $this->assertNotSoftDeleted($rule);
    }

    public function test_toggle_active_from_inactive_to_active(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create(['is_active' => false]);

        $response = $this->postJson("/api/withdrawal-rules/{$rule->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '规则已启用',
            ]);

        $rule->refresh();
        $this->assertTrue($rule->is_active);
    }

    public function test_toggle_active_from_active_to_inactive(): void
    {
        $this->actingAsPlatform();
        $rule = WithdrawalRule::factory()->create(['is_active' => true]);

        $response = $this->postJson("/api/withdrawal-rules/{$rule->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '规则已禁用',
            ]);

        $rule->refresh();
        $this->assertFalse($rule->is_active);
    }

    public function test_toggle_active_activating_deactivates_conflicts(): void
    {
        $this->actingAsPlatform();

        $rule = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => false,
        ]);

        $conflicting = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->postJson("/api/withdrawal-rules/{$rule->id}/toggle-active");

        $conflicting->refresh();
        $this->assertFalse($conflicting->is_active);
    }

    public function test_store_deactivates_conflicting_active_rules(): void
    {
        $this->actingAsPlatform();

        $existing = WithdrawalRule::factory()->create([
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'is_active' => true,
        ]);

        $this->postJson('/api/withdrawal-rules', [
            'name' => '新VIP规则',
            'code' => 'NEW_VIP_RULE',
            'user_level' => WithdrawalRule::LEVEL_VIP,
            'currency' => 'CNY',
            'withdrawal_method' => 'bank_transfer',
            'min_amount' => 100,
            'max_amount' => 100000,
            'is_active' => true,
        ]);

        $existing->refresh();
        $this->assertFalse($existing->is_active);
    }

    public function test_unauthorized_user_cannot_create_rule(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::SUPPLIER,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/withdrawal-rules', [
            'name' => '测试',
            'code' => 'UNAUTH_TEST',
        ]);

        $response->assertStatus(403);
    }

    public function test_view_current_is_publicly_accessible_to_authenticated_users(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::SUPPLIER,
            'level' => 'normal',
        ]);
        Sanctum::actingAs($user);

        WithdrawalRule::factory()
            ->active()
            ->normal()
            ->cny()
            ->bankTransfer()
            ->create();

        $response = $this->getJson('/api/withdrawal-rules/current');

        $response->assertStatus(200);
    }
}
