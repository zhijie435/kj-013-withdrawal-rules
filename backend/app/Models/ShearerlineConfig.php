<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'key', 'value', 'type', 'category', 'description', 'is_public',
])]
class ShearerlineConfig extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $config) {
            Cache::forget("shearerline_config.{$config->key}");
            Cache::forget('shearerline_configs.all');
            Cache::forget('shearerline_configs.public');
        });

        static::deleted(function (self $config) {
            Cache::forget("shearerline_config.{$config->key}");
            Cache::forget('shearerline_configs.all');
            Cache::forget('shearerline_configs.public');
        });
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeByKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function getCastedValue()
    {
        return $this->castValue($this->value, $this->type);
    }

    protected function castValue($value, string $type)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer', 'int' => (int) $value,
            'float', 'decimal', 'double' => (float) $value,
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array', 'json' => json_decode($value, true),
            default => (string) $value,
        };
    }

    public static function getValue(string $key, $default = null)
    {
        return Cache::remember("shearerline_config.{$key}", 86400, function () use ($key, $default) {
            $config = static::where('key', $key)->first();

            return $config ? $config->getCastedValue() : $default;
        });
    }

    public static function setValue(string $key, $value, array $options = []): self
    {
        $config = static::firstOrNew(['key' => $key]);
        $config->value = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        $config->type = $options['type'] ?? $config->type ?? (is_array($value) || is_object($value) ? 'array' : 'string');
        $config->category = $options['category'] ?? $config->category ?? 'general';
        $config->description = $options['description'] ?? $config->description ?? null;
        $config->is_public = $options['is_public'] ?? $config->is_public ?? false;
        $config->save();

        return $config;
    }

    public static function allByCategory(?string $category = null, bool $onlyPublic = false): array
    {
        $cacheKey = $onlyPublic ? 'shearerline_configs.public' : 'shearerline_configs.all';
        if ($category) {
            $cacheKey .= ".{$category}";
        }

        return Cache::remember($cacheKey, 86400, function () use ($category, $onlyPublic) {
            $query = static::query();

            if ($category) {
                $query->byCategory($category);
            }

            if ($onlyPublic) {
                $query->public();
            }

            return $query->get()->mapWithKeys(function (self $config) {
                return [$config->key => $config->getCastedValue()];
            })->all();
        });
    }

    public static function getWithdrawConfig(bool $onlyPublic = false): array
    {
        $raw = static::allByCategory('withdraw', $onlyPublic);

        $normalized = [];
        foreach ($raw as $key => $value) {
            $bareKey = str_starts_with($key, 'withdraw.') ? substr($key, strlen('withdraw.')) : $key;
            $normalized[$bareKey] = $value;
        }

        return $normalized;
    }

    public static function getWithdrawRule(string $key, $default = null)
    {
        $value = static::getValue("withdraw.{$key}");

        if ($value !== null) {
            return $value;
        }

        $defaults = static::getWithdrawDefaults();

        return $defaults[$key] ?? $default;
    }

    public static function getWithdrawDefaults(): array
    {
        return [
            'enabled' => true,
            'min_amount' => 100,
            'max_amount' => 50000,
            'daily_limit' => 200000,
            'monthly_limit' => 5000000,
            'fee_rate' => 0.5,
            'fee_min' => 1,
            'fee_max' => 50,
            'processing_days' => 3,
            'allow_methods' => ['bank_transfer', 'alipay', 'wechat'],
            'require_audit' => true,
            'audit_threshold' => 10000,
            'min_balance_keep' => 0,
            'freeze_days' => 0,
            'quick_amounts' => [100, 500, 1000, 2000, 5000, 10000],
        ];
    }
}
