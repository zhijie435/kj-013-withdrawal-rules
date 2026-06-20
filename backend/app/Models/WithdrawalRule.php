<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WithdrawalRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'user_level',
        'currency',
        'withdrawal_method',
        'min_amount',
        'max_amount',
        'daily_limit',
        'monthly_limit',
        'fee_rate',
        'fee_min',
        'fee_max',
        'settlement_days',
        'daily_max_count',
        'require_approval',
        'approval_threshold',
        'allowed_regions',
        'denied_regions',
        'description',
        'is_active',
        'sort_order',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
        'fee_rate' => 'decimal:4',
        'fee_min' => 'decimal:2',
        'fee_max' => 'decimal:2',
        'approval_threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'require_approval' => 'boolean',
        'allowed_regions' => 'json',
        'denied_regions' => 'json',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    const STATUS_ACTIVE = true;
    const STATUS_INACTIVE = false;

    const LEVEL_SUPER = 'super';
    const LEVEL_VIP = 'vip';
    const LEVEL_NORMAL = 'normal';
    const LEVEL_ALL = 'all';

    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ALIPAY = 'alipay';
    const METHOD_WECHAT = 'wechat';
    const METHOD_USDT = 'usdt';

    const CURRENCY_CNY = 'CNY';
    const CURRENCY_USD = 'USD';
    const CURRENCY_HKD = 'HKD';
    const CURRENCY_EUR = 'EUR';

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'label' => '启用'],
            ['value' => self::STATUS_INACTIVE, 'label' => '禁用'],
        ];
    }

    public static function getLevelOptions(): array
    {
        return [
            ['value' => self::LEVEL_ALL, 'label' => '所有等级'],
            ['value' => self::LEVEL_SUPER, 'label' => '超级用户'],
            ['value' => self::LEVEL_VIP, 'label' => 'VIP用户'],
            ['value' => self::LEVEL_NORMAL, 'label' => '普通用户'],
        ];
    }

    public static function getMethodOptions(): array
    {
        return config('withdrawal.methods');
    }

    public static function getCurrencyOptions(): array
    {
        return config('withdrawal.currencies');
    }

    public function calculateFee(float $amount): float
    {
        $fee = $amount * $this->fee_rate;

        if ($fee < $this->fee_min) {
            $fee = $this->fee_min;
        }
        if ($fee > $this->fee_max && $this->fee_max > 0) {
            $fee = $this->fee_max;
        }

        return round($fee, 2);
    }

    public function isApplicable(string $userLevel, string $currency, string $method): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->effective_from && now()->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && now()->gt($this->effective_to)) {
            return false;
        }

        if ($this->user_level !== self::LEVEL_ALL && $this->user_level !== $userLevel) {
            return false;
        }

        if ($this->currency !== $currency) {
            return false;
        }

        if ($this->withdrawal_method !== $method) {
            return false;
        }

        return true;
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
