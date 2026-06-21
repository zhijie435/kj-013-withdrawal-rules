<?php

namespace Database\Factories;

use App\Models\WithdrawalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawalRuleFactory extends Factory
{
    protected $model = WithdrawalRule::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'code' => strtoupper($this->faker->unique()->bothify('RULE_??????')),
            'user_level' => $this->faker->randomElement([
                WithdrawalRule::LEVEL_ALL,
                WithdrawalRule::LEVEL_SUPER,
                WithdrawalRule::LEVEL_VIP,
                WithdrawalRule::LEVEL_NORMAL,
            ]),
            'currency' => $this->faker->randomElement(['CNY', 'USD', 'HKD', 'EUR']),
            'withdrawal_method' => $this->faker->randomElement(['bank_transfer', 'alipay', 'wechat', 'usdt']),
            'min_amount' => $this->faker->randomFloat(2, 10, 1000),
            'max_amount' => $this->faker->randomFloat(2, 10000, 500000),
            'daily_limit' => $this->faker->randomFloat(2, 0, 200000),
            'monthly_limit' => $this->faker->randomFloat(2, 0, 1000000),
            'fee_rate' => $this->faker->randomFloat(4, 0, 0.05),
            'fixed_fee' => $this->faker->randomFloat(2, 0, 50),
            'fee_min' => $this->faker->randomFloat(2, 0, 10),
            'fee_max' => $this->faker->randomFloat(2, 0, 500),
            'settlement_days' => $this->faker->numberBetween(0, 15),
            'daily_max_count' => $this->faker->numberBetween(0, 20),
            'require_approval' => $this->faker->boolean,
            'approval_threshold' => $this->faker->randomFloat(2, 0, 100000),
            'allowed_regions' => null,
            'denied_regions' => null,
            'description' => $this->faker->paragraph,
            'is_active' => $this->faker->boolean,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'effective_from' => null,
            'effective_to' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_level' => WithdrawalRule::LEVEL_VIP,
        ]);
    }

    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_level' => WithdrawalRule::LEVEL_NORMAL,
        ]);
    }

    public function allLevels(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_level' => WithdrawalRule::LEVEL_ALL,
        ]);
    }

    public function cny(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'CNY',
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => 'USD',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'withdrawal_method' => 'bank_transfer',
        ]);
    }

    public function alipay(): static
    {
        return $this->state(fn (array $attributes) => [
            'withdrawal_method' => 'alipay',
        ]);
    }

    public function noFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'fee_rate' => 0,
            'fixed_fee' => 0,
            'fee_min' => 0,
            'fee_max' => 0,
        ]);
    }

    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_approval' => true,
        ]);
    }

    public function autoApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_approval' => false,
        ]);
    }

    public function effectiveFrom(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => $date,
        ]);
    }

    public function effectiveTo(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_to' => $date,
        ]);
    }
}
