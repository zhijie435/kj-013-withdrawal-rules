<?php

namespace Database\Factories;

use App\Models\BankCard;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\WithdrawalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawalFactory extends Factory
{
    protected $model = Withdrawal::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 100, 50000);
        $feeRate = $this->faker->randomFloat(4, 0, 0.05);
        $feeAmount = round($amount * $feeRate, 2);

        return [
            'withdrawal_no' => Withdrawal::generateNo(),
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'rule_id' => WithdrawalRule::factory(),
            'bank_card_id' => BankCard::factory(),
            'currency' => $this->faker->randomElement(['CNY', 'USD', 'HKD', 'EUR']),
            'withdrawal_method' => $this->faker->randomElement(['bank_transfer', 'alipay', 'wechat', 'usdt']),
            'request_amount' => $amount,
            'fee_rate' => $feeRate,
            'fee_amount' => $feeAmount,
            'actual_amount' => round($amount - $feeAmount, 2),
            'status' => $this->faker->randomElement([
                Withdrawal::STATUS_PENDING,
                Withdrawal::STATUS_APPROVED,
                Withdrawal::STATUS_REJECTED,
                Withdrawal::STATUS_PROCESSING,
                Withdrawal::STATUS_COMPLETED,
                Withdrawal::STATUS_FAILED,
                Withdrawal::STATUS_CANCELLED,
                Withdrawal::STATUS_SETTLED,
            ]),
            'reject_reason' => null,
            'fail_reason' => null,
            'cancel_reason' => null,
            'transaction_id' => null,
            'third_party_no' => null,
            'approved_at' => null,
            'approved_by' => null,
            'processed_at' => null,
            'processed_by' => null,
            'completed_at' => null,
            'settled_at' => null,
            'processing_note' => null,
            'audit_log' => null,
            'remark' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Withdrawal::STATUS_PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Withdrawal::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Withdrawal::STATUS_COMPLETED,
            'approved_at' => now()->subHour(),
            'processed_at' => now()->subMinutes(30),
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Withdrawal::STATUS_REJECTED,
            'reject_reason' => $this->faker->sentence,
            'approved_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Withdrawal::STATUS_FAILED,
            'fail_reason' => $this->faker->sentence,
            'processed_at' => now(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forRule(WithdrawalRule $rule): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_id' => $rule->id,
            'currency' => $rule->currency,
            'withdrawal_method' => $rule->withdrawal_method,
        ]);
    }
}
