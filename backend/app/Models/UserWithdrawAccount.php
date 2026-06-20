<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'withdraw_method_id', 'account_name',
    'account_number', 'bank_name', 'bank_branch',
    'swift_code', 'qr_code', 'is_default', 'status', 'remark',
])]
class UserWithdrawAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account) {
            if ($account->is_default) {
                self::where('user_id', $account->user_id)
                    ->where('withdraw_method_id', $account->withdraw_method_id)
                    ->where('id', '!=', $account->id)
                    ->update(['is_default' => false]);
            }
        });

        static::updating(function (self $account) {
            if ($account->is_default && $account->isDirty('is_default')) {
                self::where('user_id', $account->user_id)
                    ->where('withdraw_method_id', $account->withdraw_method_id)
                    ->where('id', '!=', $account->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawMethod::class, 'withdraw_method_id');
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByMethod(Builder $query, int $methodId): Builder
    {
        return $query->where('withdraw_method_id', $methodId);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->status;
    }

    public function setAsDefault(): self
    {
        $this->is_default = true;
        $this->save();

        return $this;
    }

    public function getAccountDisplayAttribute(): string
    {
        $masked = $this->account_number;
        if (strlen($masked) > 8) {
            $start = substr($masked, 0, 4);
            $end = substr($masked, -4);
            $masked = $start . str_repeat('*', strlen($masked) - 8) . $end;
        }

        return sprintf('%s (%s)', $this->account_name, $masked);
    }
}
