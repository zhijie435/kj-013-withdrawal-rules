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
];
