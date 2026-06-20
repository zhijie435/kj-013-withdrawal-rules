<?php

namespace Database\Seeders;

use App\Models\ShearerlineConfig;
use Illuminate\Database\Seeder;

class ShearerlineConfigSeeder extends Seeder
{
    public function run(): void
    {
        $withdrawConfigs = [
            [
                'key' => 'withdraw.enabled',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'withdraw',
                'description' => '是否启用提现功能',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.min_amount',
                'value' => '100',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '单笔最低提现金额（元）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.max_amount',
                'value' => '50000',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '单笔最高提现金额（元）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.daily_limit',
                'value' => '200000',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '每日提现总额上限（元），0 表示不限制',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.monthly_limit',
                'value' => '5000000',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '每月提现总额上限（元），0 表示不限制',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.fee_rate',
                'value' => '0.5',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '提现手续费率（%）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.fee_min',
                'value' => '1',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '最低手续费（元）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.fee_max',
                'value' => '50',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '最高手续费（元），0 表示不限制',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.processing_days',
                'value' => '3',
                'type' => 'integer',
                'category' => 'withdraw',
                'description' => '提现处理工作日',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.allow_methods',
                'value' => '["bank_transfer","alipay","wechat"]',
                'type' => 'array',
                'category' => 'withdraw',
                'description' => '允许的提现方式',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.require_audit',
                'value' => 'true',
                'type' => 'boolean',
                'category' => 'withdraw',
                'description' => '提现是否需要审核',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.audit_threshold',
                'value' => '10000',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '超过该金额需要人工审核（元）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.min_balance_keep',
                'value' => '0',
                'type' => 'decimal',
                'category' => 'withdraw',
                'description' => '账户最低保留余额（元）',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.freeze_days',
                'value' => '0',
                'type' => 'integer',
                'category' => 'withdraw',
                'description' => '提现后资金冻结天数',
                'is_public' => true,
            ],
            [
                'key' => 'withdraw.quick_amounts',
                'value' => '[100,500,1000,2000,5000,10000]',
                'type' => 'array',
                'category' => 'withdraw',
                'description' => '快捷提现金额选项',
                'is_public' => true,
            ],
        ];

        foreach ($withdrawConfigs as $config) {
            ShearerlineConfig::firstOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
