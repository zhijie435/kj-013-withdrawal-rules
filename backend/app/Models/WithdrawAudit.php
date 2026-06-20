<?php

namespace App\Models;

use App\Enums\WithdrawAuditAction;
use App\Enums\WithdrawStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'withdraw_request_id', 'auditor_id', 'action',
    'from_status', 'to_status', 'remark',
    'ip_address', 'user_agent',
])]
class WithdrawAudit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'action' => WithdrawAuditAction::class,
            'from_status' => WithdrawStatus::class,
            'to_status' => WithdrawStatus::class,
        ];
    }

    public function withdrawRequest(): BelongsTo
    {
        return $this->belongsTo(WithdrawRequest::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function getActionLabelAttribute(): string
    {
        return $this->action?->label() ?? '';
    }
}
