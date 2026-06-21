<?php

return [
    'fee_rate' => env('WITHDRAWAL_FEE_RATE', 0.005),
    'min_amount' => env('WITHDRAWAL_MIN_AMOUNT', 100),
    'max_amount' => env('WITHDRAWAL_MAX_AMOUNT', 50000),
    'daily_limit' => env('WITHDRAWAL_DAILY_LIMIT', 200000),
    'settlement_days' => env('WITHDRAWAL_SETTLEMENT_DAYS', 7),
    'methods' => [
        'bank_transfer' => '银行转账',
        'alipay' => '支付宝',
        'wechat' => '微信支付',
        'usdt' => 'USDT',
    ],
    'currencies' => [
        'CNY' => '人民币',
        'USD' => '美元',
        'HKD' => '港币',
        'EUR' => '欧元',
    ],
    'queue' => [
        'connection' => env('WITHDRAWAL_QUEUE_CONNECTION', 'redis'),
        'name' => env('WITHDRAWAL_QUEUE_NAME', 'withdrawals'),
    ],
    'auto_audit' => [
        'enabled' => env('WITHDRAWAL_AUTO_AUDIT_ENABLED', true),
        'limit' => env('WITHDRAWAL_AUTO_AUDIT_LIMIT', 50),
    ],
    'batch_process' => [
        'enabled' => env('WITHDRAWAL_BATCH_PROCESS_ENABLED', true),
        'size' => env('WITHDRAWAL_BATCH_PROCESS_SIZE', 50),
    ],
    'auto_settle_days' => env('WITHDRAWAL_AUTO_SETTLE_DAYS', 0),
    'notify_pending_hours' => env('WITHDRAWAL_NOTIFY_PENDING_HOURS', 1),
    'expired_clean_days' => env('WITHDRAWAL_EXPIRED_CLEAN_DAYS', 30),
];
