<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'user_level', 'withdraw_method_id', 'min_amount', 'max_amount',
    'daily_max_amount', 'daily_max_count', 'monthly_max_amount', 'monthly_max_count',
    'fee_rate', 'fixed_fee', 'min_fee', 'max_fee', 'processing_days',
    'requires_audit', 'status', 'remark',
])]
class WithdrawRule extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'daily_max_amount' => 'decimal:2',
            'monthly_max_amount' => 'decimal:2',
            'fee_rate' => 'decimal:4',
            'fixed_fee' => 'decimal:2',
            'min_fee' => 'decimal:2',
            'max_fee' => 'decimal:2',
            'daily_max_count' => 'integer',
            'monthly_max_count' => 'integer',
            'processing_days' => 'integer',
            'requires_audit' => 'boolean',
            'status' => 'boolean',
        ];
    }

    protected static function getGlobalEnabled(): bool
    {
        return (bool) ShearerlineConfig::getWithdrawRule('enabled', true);
    }

    public static function isGloballyEnabled(): bool
    {
        return self::getGlobalEnabled();
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawMethod::class, 'withdraw_method_id');
    }

    public function withdrawRequests(): HasMany
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeByUserLevel(Builder $query, string $userLevel): Builder
    {
        return $query->where('user_level', $userLevel);
    }

    public function scopeByMethod(Builder $query, int $methodId): Builder
    {
        return $query->where('withdraw_method_id', $methodId);
    }

    public function isEnabled(): bool
    {
        return self::getGlobalEnabled()
            && (bool) $this->status
            && $this->method?->isEnabled();
    }

    public function calculateFee(float $amount): float
    {
        $fee = $amount * $this->fee_rate + $this->fixed_fee;

        if ($this->min_fee > 0 && $fee < $this->min_fee) {
            $fee = $this->min_fee;
        }

        if ($this->max_fee > 0 && $fee > $this->max_fee) {
            $fee = $this->max_fee;
        }

        return round($fee, 2);
    }

    public function isValidAmount(float $amount): bool
    {
        if ($amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount > 0 && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    public static function getRule(string $userLevel, int $methodId): ?self
    {
        return self::where('user_level', $userLevel)
            ->where('withdraw_method_id', $methodId)
            ->where('status', true)
            ->first();
    }
}
