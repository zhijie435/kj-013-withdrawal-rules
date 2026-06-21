<?php

namespace Database\Factories;

use App\Models\BankCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankCardFactory extends Factory
{
    protected $model = BankCard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'card_type' => $this->faker->randomElement([
                BankCard::TYPE_DEBIT,
                BankCard::TYPE_CREDIT,
                BankCard::TYPE_ALIPAY,
                BankCard::TYPE_WECHAT,
                BankCard::TYPE_USDT,
            ]),
            'bank_name' => $this->faker->randomElement([
                '中国工商银行',
                '中国建设银行',
                '中国农业银行',
                '中国银行',
                '招商银行',
            ]),
            'bank_code' => $this->faker->randomElement(['ICBC', 'CCB', 'ABC', 'BOC', 'CMB']),
            'branch_name' => $this->faker->city . '支行',
            'card_number' => $this->faker->creditCardNumber,
            'card_holder_name' => $this->faker->name,
            'currency' => $this->faker->randomElement(['CNY', 'USD', 'HKD', 'EUR']),
            'province' => $this->faker->state,
            'city' => $this->faker->city,
            'swift_code' => null,
            'iban' => null,
            'is_default' => false,
            'is_verified' => $this->faker->boolean,
            'verified_at' => $this->faker->boolean ? now() : null,
            'is_active' => true,
            'remark' => null,
        ];
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => BankCard::TYPE_DEBIT,
        ]);
    }

    public function alipay(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => BankCard::TYPE_ALIPAY,
        ]);
    }

    public function wechat(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_type' => BankCard::TYPE_WECHAT,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
