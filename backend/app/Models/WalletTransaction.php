<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_no',
        'wallet_id',
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'currency',
        'related_type',
        'related_id',
        'description',
        'status',
        'operator_id',
        'remark',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    const TYPE_RECHARGE = 'recharge';
    const TYPE_WITHDRAWAL = 'withdrawal';
    const TYPE_WITHDRAWAL_FEE = 'withdrawal_fee';
    const TYPE_WITHDRAWAL_REFUND = 'withdrawal_refund';
    const TYPE_ORDER_INCOME = 'order_income';
    const TYPE_ORDER_REFUND = 'order_refund';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_ADJUST = 'adjust';
    const TYPE_SETTLEMENT = 'settlement';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    public static function generateNo(): string
    {
        $prefix = 'TXN';
        $date = now()->format('YmdHis');
        $random = strtoupper(Str::random(8));
        return "{$prefix}{$date}{$random}";
    }

    public static function getTypeOptions(): array
    {
        return [
            ['value' => self::TYPE_RECHARGE, 'label' => '充值'],
            ['value' => self::TYPE_WITHDRAWAL, 'label' => '提现'],
            ['value' => self::TYPE_WITHDRAWAL_FEE, 'label' => '提现手续费'],
            ['value' => self::TYPE_WITHDRAWAL_REFUND, 'label' => '提现退款'],
            ['value' => self::TYPE_ORDER_INCOME, 'label' => '订单收入'],
            ['value' => self::TYPE_ORDER_REFUND, 'label' => '订单退款'],
            ['value' => self::TYPE_TRANSFER_IN, 'label' => '转入'],
            ['value' => self::TYPE_TRANSFER_OUT, 'label' => '转出'],
            ['value' => self::TYPE_ADJUST, 'label' => '调账'],
            ['value' => self::TYPE_SETTLEMENT, 'label' => '结算'],
        ];
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function related()
    {
        return $this->morphTo();
    }
}
