<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'card_type',
        'bank_name',
        'bank_code',
        'branch_name',
        'card_number',
        'card_holder_name',
        'currency',
        'province',
        'city',
        'swift_code',
        'iban',
        'is_default',
        'is_verified',
        'verified_at',
        'is_active',
        'remark',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
    ];

    const TYPE_DEBIT = 'debit';
    const TYPE_CREDIT = 'credit';
    const TYPE_ALIPAY = 'alipay';
    const TYPE_WECHAT = 'wechat';
    const TYPE_USDT = 'usdt';

    public static function getTypeOptions(): array
    {
        return [
            ['value' => self::TYPE_DEBIT, 'label' => '借记卡'],
            ['value' => self::TYPE_CREDIT, 'label' => '信用卡'],
            ['value' => self::TYPE_ALIPAY, 'label' => '支付宝'],
            ['value' => self::TYPE_WECHAT, 'label' => '微信支付'],
            ['value' => self::TYPE_USDT, 'label' => 'USDT钱包'],
        ];
    }

    public static function getBankOptions(): array
    {
        return [
            ['value' => 'ICBC', 'label' => '中国工商银行'],
            ['value' => 'CCB', 'label' => '中国建设银行'],
            ['value' => 'ABC', 'label' => '中国农业银行'],
            ['value' => 'BOC', 'label' => '中国银行'],
            ['value' => 'CMB', 'label' => '招商银行'],
            ['value' => 'SPDB', 'label' => '浦发银行'],
            ['value' => 'CMBC', 'label' => '中国民生银行'],
            ['value' => 'PAB', 'label' => '平安银行'],
            ['value' => 'CITIC', 'label' => '中信银行'],
            ['value' => 'HXB', 'label' => '华夏银行'],
            ['value' => 'CGB', 'label' => '广发银行'],
            ['value' => 'BOCOM', 'label' => '交通银行'],
            ['value' => 'PSBC', 'label' => '中国邮政储蓄银行'],
        ];
    }

    public function getMaskedCardNumberAttribute(): string
    {
        $len = strlen($this->card_number);
        if ($len <= 8) {
            return $this->card_number;
        }
        $start = substr($this->card_number, 0, 4);
        $end = substr($this->card_number, -4);
        return "{$start} **** **** {$end}";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }

    protected static function booted(): void
    {
        static::creating(function (BankCard $card) {
            if ($card->is_default) {
                BankCard::where('user_id', $card->user_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function (BankCard $card) {
            if ($card->is_default && $card->isDirty('is_default')) {
                BankCard::where('user_id', $card->user_id)
                    ->where('id', '!=', $card->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }
}
