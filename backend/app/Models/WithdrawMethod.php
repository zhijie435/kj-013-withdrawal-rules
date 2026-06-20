<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'name', 'icon', 'description', 'currency',
    'sort', 'status', 'config',
])]
class WithdrawMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort' => 'integer',
            'config' => 'array',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(WithdrawRule::class);
    }

    public function withdrawRequests(): HasMany
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(UserWithdrawAccount::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->status;
    }
}
