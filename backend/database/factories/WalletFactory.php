<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        $balance = $this->faker->randomFloat(2, 0, 500000);

        return [
            'user_id' => User::factory(),
            'currency' => $this->faker->randomElement(['CNY', 'USD', 'HKD', 'EUR']),
            'balance' => $balance,
            'frozen_amount' => $this->faker->randomFloat(2, 0, $balance / 2),
            'pending_settle_amount' => $this->faker->randomFloat(2, 0, 10000),
            'total_withdrawn' => $this->faker->randomFloat(2, 0, 100000),
            'total_recharge' => $this->faker->randomFloat(2, 0, 500000),
            'today_withdrawn' => $this->faker->randomFloat(2, 0, 50000),
            'last_withdraw_date' => $this->faker->boolean ? now()->toDateString() : null,
            'is_active' => true,
            'remark' => null,
        ];
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

    public function withBalance(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $amount,
            'frozen_amount' => 0,
        ]);
    }

    public function withSufficientBalance(float $withdrawAmount): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $withdrawAmount + $this->faker->randomFloat(2, 1000, 50000),
            'frozen_amount' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
