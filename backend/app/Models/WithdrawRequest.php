<?php

namespace App\Models;

use App\Enums\WithdrawAuditAction;
use App\Enums\WithdrawStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'request_no', 'user_id', 'distributor_id', 'withdraw_method_id',
    'withdraw_rule_id', 'amount', 'fee', 'actual_amount', 'currency',
    'account_info', 'remark', 'status', 'auditor_id', 'audit_time',
    'audit_remark', 'transaction_no', 'processed_at', 'failure_reason',
    'ip_address', 'user_agent',
])]
class WithdrawRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'status' => WithdrawStatus::class,
            'account_info' => 'array',
            'audit_time' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $withdraw) {
            if (empty($withdraw->request_no)) {
                $withdraw->request_no = self::generateRequestNo();
            }
        });
    }

    public static function generateRequestNo(): string
    {
        return 'WD' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawMethod::class, 'withdraw_method_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WithdrawRule::class, 'withdraw_rule_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function auditRecords(): HasMany
    {
        return $this->hasMany(WithdrawAudit::class)->orderBy('created_at', 'desc');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(UserWithdrawAccount::class, 'user_id', 'user_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '';
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status?->color() ?? 'info';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::PENDING->value);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::APPROVED->value);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::PROCESSING->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::COMPLETED->value);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::REJECTED->value);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::FAILED->value);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', WithdrawStatus::CANCELLED->value);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDistributor(Builder $query, int $distributorId): Builder
    {
        return $query->where('distributor_id', $distributorId);
    }

    public function scopeByMethod(Builder $query, int $methodId): Builder
    {
        return $query->where('withdraw_method_id', $methodId);
    }

    public function isPending(): bool
    {
        return $this->status === WithdrawStatus::PENDING;
    }

    public function canCancel(): bool
    {
        return $this->status?->canCancel() ?? false;
    }

    public function canAudit(): bool
    {
        return $this->status?->canAudit() ?? false;
    }

    public function canTransitionTo(WithdrawStatus $status): bool
    {
        return $this->status?->canTransitionTo($status) ?? false;
    }
}
