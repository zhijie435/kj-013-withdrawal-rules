<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Withdrawal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'withdrawal_no',
        'user_id',
        'wallet_id',
        'rule_id',
        'bank_card_id',
        'currency',
        'withdrawal_method',
        'request_amount',
        'fee_rate',
        'fee_amount',
        'actual_amount',
        'status',
        'reject_reason',
        'fail_reason',
        'cancel_reason',
        'transaction_id',
        'third_party_no',
        'approved_at',
        'approved_by',
        'processed_at',
        'processed_by',
        'completed_at',
        'settled_at',
        'processing_note',
        'audit_log',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'request_amount' => 'decimal:2',
        'fee_rate' => 'decimal:4',
        'fee_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'audit_log' => 'json',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SETTLED = 'settled';

    public static function generateNo(): string
    {
        $prefix = 'WD';
        $date = now()->format('YmdHis');
        $random = strtoupper(Str::random(8));
        return "{$prefix}{$date}{$random}";
    }

    public static function getStatusOptions(): array
    {
        return [
            ['value' => self::STATUS_PENDING, 'label' => '待审核'],
            ['value' => self::STATUS_APPROVED, 'label' => '已通过'],
            ['value' => self::STATUS_REJECTED, 'label' => '已拒绝'],
            ['value' => self::STATUS_PROCESSING, 'label' => '处理中'],
            ['value' => self::STATUS_COMPLETED, 'label' => '已完成'],
            ['value' => self::STATUS_FAILED, 'label' => '打款失败'],
            ['value' => self::STATUS_CANCELLED, 'label' => '已取消'],
            ['value' => self::STATUS_SETTLED, 'label' => '已结算'],
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => '待审核',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已拒绝',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '打款失败',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_SETTLED => '已结算',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function rule()
    {
        return $this->belongsTo(WithdrawalRule::class, 'rule_id');
    }

    public function bankCard()
    {
        return $this->belongsTo(BankCard::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING], true);
    }

    public function canApprove(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING], true);
    }

    public function canReject(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING], true);
    }

    public function canProcess(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED], true);
    }

    public function canComplete(): bool
    {
        return in_array($this->status, [self::STATUS_PROCESSING], true);
    }

    public function canFail(): bool
    {
        return in_array($this->status, [self::STATUS_PROCESSING], true);
    }

    public function addAuditLog(string $action, string $remark = '', ?int $userId = null): void
    {
        $logs = $this->audit_log ?? [];
        $logs[] = [
            'action' => $action,
            'user_id' => $userId ?? auth()->id(),
            'user_name' => $userId ? User::find($userId)?->name : auth()->user()?->name,
            'remark' => $remark,
            'time' => now()->toDateTimeString(),
        ];
        $this->update(['audit_log' => $logs]);
    }
}
