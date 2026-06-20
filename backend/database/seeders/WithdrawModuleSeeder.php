<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class WithdrawModuleSeeder extends Seeder
{
    public function run()
    {
        // 创建权限
        $permissions = [
            'withdraw.view',
            'withdraw.create',
            'withdraw.approve',
            'withdraw.reject',
            'withdraw.cancel',
            'withdraw.process',
            'withdraw-rule.view',
            'withdraw-rule.manage',
            'withdraw-method.view',
            'withdraw-method.manage',
            'withdraw-account.view',
            'withdraw-account.manage',
            'wallet.view',
            'wallet.manage',
            'user.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 创建角色
        $roles = [
            'super_admin' => '超级管理员',
            'finance' => '财务人员',
            'operation' => '运营人员',
            'user' => '普通用户',
        ];

        foreach ($roles as $code => $name) {
            $roleApi = Role::firstOrCreate(['name' => $code, 'guard_name' => 'api']);
            $roleWeb = Role::firstOrCreate(['name' => $code, 'guard_name' => 'web']);

            if ($code === 'super_admin') {
                $roleApi->syncPermissions($permissions);
                $roleWeb->syncPermissions($permissions);
            } elseif ($code === 'finance') {
                $roleApi->syncPermissions([
                    'withdraw.view', 'withdraw.approve', 'withdraw.reject',
                    'withdraw.process', 'withdraw-rule.view', 'withdraw-method.view',
                    'wallet.view', 'withdraw-account.view'
                ]);
                $roleWeb->syncPermissions([
                    'withdraw.view', 'withdraw.approve', 'withdraw.reject',
                    'withdraw.process', 'withdraw-rule.view', 'withdraw-method.view',
                    'wallet.view', 'withdraw-account.view'
                ]);
            } elseif ($code === 'operation') {
                $roleApi->syncPermissions([
                    'withdraw.view', 'withdraw-rule.view', 'withdraw-rule.manage',
                    'withdraw-method.view', 'withdraw-method.manage', 'wallet.view'
                ]);
                $roleWeb->syncPermissions([
                    'withdraw.view', 'withdraw-rule.view', 'withdraw-rule.manage',
                    'withdraw-method.view', 'withdraw-method.manage', 'wallet.view'
                ]);
            } elseif ($code === 'user') {
                $roleApi->syncPermissions([
                    'withdraw.view', 'withdraw.create', 'withdraw.cancel',
                    'withdraw-account.view', 'withdraw-account.manage', 'wallet.view'
                ]);
                $roleWeb->syncPermissions([
                    'withdraw.view', 'withdraw.create', 'withdraw.cancel',
                    'withdraw-account.view', 'withdraw-account.manage', 'wallet.view'
                ]);
            }
        }

        // 创建默认提现方式
        $methods = [
            [
                'code' => 'bank_transfer',
                'name' => '银行转账',
                'description' => '支持境内所有银行的对公/对私转账',
                'currency' => 'CNY',
                'sort' => 1,
                'status' => true,
                'config' => [
                    'fee_rate' => 0.5,
                    'fee_min' => 2,
                    'fee_max' => 50,
                    'min_amount' => 100,
                    'max_amount' => 500000,
                ],
            ],
            [
                'code' => 'alipay',
                'name' => '支付宝',
                'description' => '支付宝企业/个人账户转账',
                'currency' => 'CNY',
                'sort' => 2,
                'status' => true,
                'config' => [
                    'fee_rate' => 0.3,
                    'fee_min' => 1,
                    'fee_max' => 30,
                    'min_amount' => 10,
                    'max_amount' => 50000,
                ],
            ],
            [
                'code' => 'wechat',
                'name' => '微信支付',
                'description' => '微信企业/个人账户转账',
                'currency' => 'CNY',
                'sort' => 3,
                'status' => true,
                'config' => [
                    'fee_rate' => 0.3,
                    'fee_min' => 1,
                    'fee_max' => 30,
                    'min_amount' => 10,
                    'max_amount' => 50000,
                ],
            ],
        ];

        $createdMethods = [];
        foreach ($methods as $method) {
            $created = WithdrawMethod::firstOrCreate(['code' => $method['code']], $method);
            $createdMethods[$method['code']] = $created->id;
        }

        // 创建默认提现规则
        $rules = [
            [
                'name' => '普通用户提现规则',
                'user_level' => 'normal',
                'withdraw_method_id' => $createdMethods['bank_transfer'] ?? 1,
                'min_amount' => 100,
                'max_amount' => 50000,
                'daily_max_amount' => 50000,
                'daily_max_count' => 3,
                'monthly_max_amount' => 200000,
                'monthly_max_count' => 20,
                'fee_rate' => 0.005,
                'fixed_fee' => 0,
                'min_fee' => 2,
                'max_fee' => 50,
                'processing_days' => 3,
                'requires_audit' => true,
                'status' => true,
                'remark' => '适用于普通分销商的默认提现规则',
            ],
            [
                'name' => 'VIP用户提现规则',
                'user_level' => 'vip',
                'withdraw_method_id' => $createdMethods['bank_transfer'] ?? 1,
                'min_amount' => 100,
                'max_amount' => 100000,
                'daily_max_amount' => 100000,
                'daily_max_count' => 5,
                'monthly_max_amount' => 500000,
                'monthly_max_count' => 50,
                'fee_rate' => 0.003,
                'fixed_fee' => 0,
                'min_fee' => 1,
                'max_fee' => 30,
                'processing_days' => 2,
                'requires_audit' => true,
                'status' => true,
                'remark' => 'VIP用户享受更低手续费',
            ],
            [
                'name' => 'SVIP用户免手续费规则',
                'user_level' => 'svip',
                'withdraw_method_id' => $createdMethods['bank_transfer'] ?? 1,
                'min_amount' => 100,
                'max_amount' => 500000,
                'daily_max_amount' => 500000,
                'daily_max_count' => 10,
                'monthly_max_amount' => 2000000,
                'monthly_max_count' => 100,
                'fee_rate' => 0,
                'fixed_fee' => 0,
                'min_fee' => 0,
                'max_fee' => 0,
                'processing_days' => 1,
                'requires_audit' => false,
                'status' => true,
                'remark' => 'SVIP及以上用户免手续费，自动通过',
            ],
            [
                'name' => '普通用户支付宝提现',
                'user_level' => 'normal',
                'withdraw_method_id' => $createdMethods['alipay'] ?? 2,
                'min_amount' => 10,
                'max_amount' => 50000,
                'daily_max_amount' => 50000,
                'daily_max_count' => 5,
                'monthly_max_amount' => 200000,
                'monthly_max_count' => 30,
                'fee_rate' => 0.003,
                'fixed_fee' => 0,
                'min_fee' => 1,
                'max_fee' => 30,
                'processing_days' => 1,
                'requires_audit' => true,
                'status' => true,
                'remark' => '支付宝提现规则',
            ],
            [
                'name' => '普通用户微信提现',
                'user_level' => 'normal',
                'withdraw_method_id' => $createdMethods['wechat'] ?? 3,
                'min_amount' => 10,
                'max_amount' => 50000,
                'daily_max_amount' => 50000,
                'daily_max_count' => 5,
                'monthly_max_amount' => 200000,
                'monthly_max_count' => 30,
                'fee_rate' => 0.003,
                'fixed_fee' => 0,
                'min_fee' => 1,
                'max_fee' => 30,
                'processing_days' => 1,
                'requires_audit' => true,
                'status' => true,
                'remark' => '微信支付提现规则',
            ],
        ];

        foreach ($rules as $rule) {
            WithdrawRule::firstOrCreate(
                [
                    'user_level' => $rule['user_level'],
                    'withdraw_method_id' => $rule['withdraw_method_id'],
                ],
                $rule
            );
        }

        // 创建测试用户
        $user = User::firstOrCreate(
            ['email' => 'test@shearerline.com'],
            [
                'name' => '测试用户',
                'password' => Hash::make('password123'),
                'level' => 'normal',
            ]
        );
        if (!$user->hasRole('user')) {
            $user->assignRole('user');
        }

        // 为用户创建钱包
        if (!$user->wallets()->where('currency', 'CNY')->exists()) {
            $user->wallets()->create([
                'currency' => 'CNY',
                'balance' => 100000.00,
                'frozen_amount' => 0,
                'total_recharge' => 100000.00,
                'total_withdrawn' => 0,
            ]);
        }

        // 创建管理员用户
        $admin = User::firstOrCreate(
            ['email' => 'admin@shearerline.com'],
            [
                'name' => '超级管理员',
                'password' => Hash::make('admin123'),
                'level' => 'svip',
            ]
        );
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        if (!$admin->wallets()->where('currency', 'CNY')->exists()) {
            $admin->wallets()->create([
                'currency' => 'CNY',
                'balance' => 500000.00,
                'frozen_amount' => 0,
                'total_recharge' => 500000.00,
                'total_withdrawn' => 0,
            ]);
        }

        // 创建财务人员
        $finance = User::firstOrCreate(
            ['email' => 'finance@shearerline.com'],
            [
                'name' => '财务人员',
                'password' => Hash::make('finance123'),
                'level' => 'normal',
            ]
        );
        if (!$finance->hasRole('finance')) {
            $finance->assignRole('finance');
        }

        $this->command->info('提现模块数据填充完成！');
        $this->command->line('测试账号：');
        $this->command->line('  超级管理员: admin@shearerline.com / admin123');
        $this->command->line('  财务人员: finance@shearerline.com / finance123');
        $this->command->line('  普通用户: test@shearerline.com / password123');
    }
}
