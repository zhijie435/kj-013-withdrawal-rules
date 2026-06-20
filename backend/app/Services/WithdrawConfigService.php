<?php

namespace App\Services;

use App\Models\ShearerlineConfig;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRule;
use Illuminate\Support\Facades\DB;

class WithdrawConfigService
{
    protected array $syncFieldMap = [
        'min_amount' => 'min_amount',
        'max_amount' => 'max_amount',
        'daily_limit' => 'daily_max_amount',
        'monthly_limit' => 'monthly_max_amount',
        'fee_rate' => 'fee_rate',
        'fee_min' => 'min_fee',
        'fee_max' => 'max_fee',
        'processing_days' => 'processing_days',
        'require_audit' => 'requires_audit',
    ];

    protected function getSyncableUpdates(array $data): array
    {
        $updates = [];

        foreach ($this->syncFieldMap as $globalKey => $ruleKey) {
            if (array_key_exists($globalKey, $data)) {
                $value = $data[$globalKey];
                if ($globalKey === 'fee_rate') {
                    $value = bcdiv((string) $value, '100', 4);
                }
                $updates[$ruleKey] = $value;
            }
        }

        return $updates;
    }

    protected function syncGlobalConfigToRules(array $data): void
    {
        $updates = $this->getSyncableUpdates($data);
        if (empty($updates)) {
            return;
        }

        DB::transaction(function () use ($updates, $data) {
            WithdrawRule::query()->update($updates);

            if (isset($data['allow_methods'])) {
                $allowedMethods = $data['allow_methods'];
                WithdrawMethod::query()->each(function (WithdrawMethod $method) use ($allowedMethods) {
                    $shouldBeEnabled = in_array($method->code, $allowedMethods, true);
                    if ((bool) $method->status !== $shouldBeEnabled) {
                        $method->status = $shouldBeEnabled;
                        $method->save();
                    }
                });
            }
        });
    }

    public function getAll(bool $onlyPublic = false): array
    {
        $withdrawConfig = ShearerlineConfig::getWithdrawConfig($onlyPublic);
        $defaults = ShearerlineConfig::getWithdrawDefaults();

        foreach ($defaults as $key => $value) {
            if (!isset($withdrawConfig[$key])) {
                $withdrawConfig[$key] = $value;
            }
        }

        return $withdrawConfig;
    }

    public function getAllWithDefaults(bool $onlyPublic = false): array
    {
        $config = $this->getAll($onlyPublic);

        $synced = [];
        foreach ($this->syncFieldMap as $globalKey => $ruleKey) {
            $synced[$globalKey] = $config[$globalKey] ?? null;
        }

        return [
            'data' => $config,
            'defaults' => ShearerlineConfig::getWithdrawDefaults(),
            'sync_fields' => array_keys($this->syncFieldMap),
            'synced_values' => $synced,
            'global_enabled' => $config['enabled'] ?? true,
        ];
    }

    public function getRule(string $key, $default = null)
    {
        return ShearerlineConfig::getWithdrawRule($key, $default);
    }

    public function update(array $data): array
    {
        $typeMap = [
            'enabled' => 'boolean',
            'min_amount' => 'decimal',
            'max_amount' => 'decimal',
            'daily_limit' => 'decimal',
            'monthly_limit' => 'decimal',
            'fee_rate' => 'decimal',
            'fee_min' => 'decimal',
            'fee_max' => 'decimal',
            'processing_days' => 'integer',
            'allow_methods' => 'array',
            'require_audit' => 'boolean',
            'audit_threshold' => 'decimal',
            'min_balance_keep' => 'decimal',
            'freeze_days' => 'integer',
            'quick_amounts' => 'array',
        ];

        $descriptionMap = [
            'enabled' => '是否启用提现功能',
            'min_amount' => '单笔最低提现金额（元）',
            'max_amount' => '单笔最高提现金额（元）',
            'daily_limit' => '每日提现总额上限（元），0 表示不限制',
            'monthly_limit' => '每月提现总额上限（元），0 表示不限制',
            'fee_rate' => '提现手续费率（%）',
            'fee_min' => '最低手续费（元）',
            'fee_max' => '最高手续费（元），0 表示不限制',
            'processing_days' => '提现处理工作日',
            'allow_methods' => '允许的提现方式',
            'require_audit' => '提现是否需要审核',
            'audit_threshold' => '超过该金额需要人工审核（元）',
            'min_balance_keep' => '账户最低保留余额（元）',
            'freeze_days' => '提现后资金冻结天数',
            'quick_amounts' => '快捷提现金额选项',
        ];

        DB::transaction(function () use ($data, $typeMap, $descriptionMap) {
            foreach ($data as $key => $value) {
                $configKey = "withdraw.{$key}";
                ShearerlineConfig::setValue($configKey, $value, [
                    'type' => $typeMap[$key] ?? 'string',
                    'category' => 'withdraw',
                    'description' => $descriptionMap[$key] ?? null,
                    'is_public' => true,
                ]);
            }

            $this->syncGlobalConfigToRules($data);
        });

        return $this->getAll();
    }

    public function calculateFee(float $amount): float
    {
        $feeRate = $this->getRule('fee_rate', 0);
        $feeMin = $this->getRule('fee_min', 0);
        $feeMax = $this->getRule('fee_max', 0);

        $fee = $amount * ($feeRate / 100);

        if ($fee < $feeMin) {
            $fee = $feeMin;
        }

        if ($feeMax > 0 && $fee > $feeMax) {
            $fee = $feeMax;
        }

        return round($fee, 2);
    }
}
