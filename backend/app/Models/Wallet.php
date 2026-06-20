<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'frozen_amount',
        'pending_settle_amount',
        'total_withdrawn',
        'total_recharge',
        'today_withdrawn',
        'last_withdraw_date',
        'is_active',
        'remark',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'frozen_amount' => 'decimal:2',
        'pending_settle_amount' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'total_recharge' => 'decimal:2',
        'today_withdrawn' => 'decimal:2',
        'is_active' => 'boolean',
        'last_withdraw_date' => 'date',
    ];

    protected $appends = [
        'available_balance',
    ];

    public function getAvailableBalanceAttribute()
    {
        return bcsub($this->balance, $this->frozen_amount, 2);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function canWithdraw(float $amount): bool
    {
        return $this->available_balance >= $amount && $this->is_active;
    }

    public function incrementBalance(float $amount, string $type, array $extra = []): WalletTransaction
    {
        return \DB::transaction(function () use ($amount, $type, $extra) {
            $balanceBefore = $this->balance;
            $this->increment('balance', $amount);
            $this->refresh();

            return WalletTransaction::create(array_merge([
                'transaction_no' => WalletTransaction::generateNo(),
                'wallet_id' => $this->id,
                'user_id' => $this->user_id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'currency' => $this->currency,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ], $extra));
        });
    }

    public function decrementBalance(float $amount, string $type, array $extra = []): WalletTransaction
    {
        return \DB::transaction(function () use ($amount, $type, $extra) {
            if ($this->balance < $amount) {
                throw new \App\Exceptions\Withdrawal\InsufficientBalanceException('余额不足');
            }

            $balanceBefore = $this->balance;
            $this->decrement('balance', $amount);
            $this->refresh();

            return WalletTransaction::create(array_merge([
                'transaction_no' => WalletTransaction::generateNo(),
                'wallet_id' => $this->id,
                'user_id' => $this->user_id,
                'type' => $type,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $this->balance,
                'currency' => $this->currency,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ], $extra));
        });
    }

    public function freeze(float $amount): void
    {
        if ($this->available_balance < $amount) {
            throw new \App\Exceptions\Withdrawal\InsufficientBalanceException('可用余额不足');
        }
        $this->increment('frozen_amount', $amount);
    }

    public function unfreeze(float $amount): void
    {
        if ($this->frozen_amount < $amount) {
            throw new \App\Exceptions\Withdrawal\WithdrawalException('解冻金额异常');
        }
        $this->decrement('frozen_amount', $amount);
    }
}
