# Shearerline 提现规则模块 - 本地启动说明

## 一、环境要求

| 软件 | 版本要求 | 说明 |
|------|----------|------|
| PHP | >= 8.2 | 支持PHP 8.1枚举、只读属性等新特性 |
| Laravel | 11.x | 使用最新的Laravel 11框架 |
| MySQL | >= 8.0 | 支持JSON字段、CTE等特性 |
| Redis | >= 6.0 | 用于队列和缓存 |
| Node.js | >= 18.x | 用于前端构建 |
| NPM/Yarn | >= 9.x | 包管理工具 |
| Composer | >= 2.x | PHP依赖管理 |

## 二、安装步骤

### 2.1 克隆项目并安装依赖

```bash
# 进入项目目录
cd backend

# 安装PHP依赖
composer install

# 安装前端依赖
npm install
```

### 2.2 环境配置

```bash
# 复制环境配置文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate
```

### 2.3 配置数据库和Redis

编辑 `.env` 文件：

```dotenv
# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shearerline
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis配置
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# 队列配置
QUEUE_CONNECTION=redis

# 会话配置
SESSION_DRIVER=database
```

### 2.4 运行数据库迁移

```bash
# 创建数据库
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS shearerline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 运行迁移（包含提现模块的所有表）
php artisan migrate

# 安装passport（如果需要API认证）
php artisan passport:install
```

### 2.5 安装权限系统并创建初始数据

```bash
# 创建缓存表
php artisan cache:table

# 创建权限相关表（已在迁移中包含）
# 填充初始数据：角色、权限、提现方式、默认规则等
php artisan db:seed --class=WithdrawModuleSeeder
```

如果还没有创建Seeder，创建 `database/seeders/WithdrawModuleSeeder.php`：

```php
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
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // 创建角色
        $roles = [
            'super_admin' => '超级管理员',
            'finance' => '财务人员',
            'operation' => '运营人员',
            'user' => '普通用户',
        ];

        foreach ($roles as $code => $name) {
            $role = Role::create(['name' => $code, 'guard_name' => 'api']);
            Role::create(['name' => $code, 'guard_name' => 'web']);

            if ($code === 'super_admin') {
                $role->syncPermissions($permissions);
            } elseif ($code === 'finance') {
                $role->syncPermissions([
                    'withdraw.view', 'withdraw.approve', 'withdraw.reject',
                    'withdraw.process', 'withdraw-rule.view', 'withdraw-method.view',
                    'wallet.view', 'withdraw-account.view'
                ]);
            } elseif ($code === 'operation') {
                $role->syncPermissions([
                    'withdraw.view', 'withdraw-rule.view', 'withdraw-rule.manage',
                    'withdraw-method.view', 'withdraw-method.manage', 'wallet.view'
                ]);
            } elseif ($code === 'user') {
                $role->syncPermissions([
                    'withdraw.view', 'withdraw.create', 'withdraw.cancel',
                    'withdraw-account.view', 'withdraw-account.manage', 'wallet.view'
                ]);
            }
        }

        // 创建默认提现方式
        $methods = [
            [
                'name' => '银行转账',
                'code' => 'bank_transfer',
                'description' => '支持境内所有银行的对公/对私转账',
                'fee_type' => 'percentage',
                'fee_value' => 0.5,
                'min_fee' => 2,
                'max_fee' => 50,
                'min_amount' => 100,
                'max_amount' => 500000,
                'daily_limit' => 500000,
                'monthly_limit' => 5000000,
                'sort_order' => 1,
                'is_enabled' => true,
            ],
            [
                'name' => '支付宝',
                'code' => 'alipay',
                'description' => '支付宝企业/个人账户转账',
                'fee_type' => 'percentage',
                'fee_value' => 0.3,
                'min_fee' => 1,
                'max_fee' => 30,
                'min_amount' => 10,
                'max_amount' => 50000,
                'daily_limit' => 50000,
                'monthly_limit' => 500000,
                'sort_order' => 2,
                'is_enabled' => true,
            ],
            [
                'name' => '微信支付',
                'code' => 'wechat',
                'description' => '微信企业/个人账户转账',
                'fee_type' => 'percentage',
                'fee_value' => 0.3,
                'min_fee' => 1,
                'max_fee' => 30,
                'min_amount' => 10,
                'max_amount' => 50000,
                'daily_limit' => 50000,
                'monthly_limit' => 500000,
                'sort_order' => 3,
                'is_enabled' => true,
            ],
        ];

        foreach ($methods as $method) {
            WithdrawMethod::create($method);
        }

        // 创建默认提现规则
        $rules = [
            [
                'name' => '普通用户提现规则',
                'description' => '适用于普通用户的默认提现规则',
                'method' => 'bank_transfer',
                'user_level' => 0,
                'min_amount' => 100,
                'max_amount' => 50000,
                'fee_type' => 'percentage',
                'fee_value' => 0.5,
                'min_fee' => 2,
                'max_fee' => 50,
                'daily_limit' => 50000,
                'monthly_limit' => 200000,
                'daily_quota' => 3,
                'is_enabled' => true,
            ],
            [
                'name' => 'VIP1用户提现规则',
                'description' => 'VIP1用户享受更低手续费',
                'method' => 'bank_transfer',
                'user_level' => 1,
                'min_amount' => 100,
                'max_amount' => 100000,
                'fee_type' => 'percentage',
                'fee_value' => 0.3,
                'min_fee' => 1,
                'max_fee' => 30,
                'daily_limit' => 100000,
                'monthly_limit' => 500000,
                'daily_quota' => 5,
                'is_enabled' => true,
            ],
            [
                'name' => 'VIP3用户免手续费',
                'description' => 'VIP3及以上用户免手续费',
                'method' => 'bank_transfer',
                'user_level' => 3,
                'min_amount' => 100,
                'max_amount' => 500000,
                'fee_type' => 'free',
                'fee_value' => 0,
                'min_fee' => 0,
                'max_fee' => 0,
                'daily_limit' => 500000,
                'monthly_limit' => 2000000,
                'daily_quota' => 10,
                'is_enabled' => true,
            ],
        ];

        foreach ($rules as $rule) {
            WithdrawRule::create($rule);
        }

        // 创建测试用户
        $user = User::create([
            'name' => '测试用户',
            'email' => 'test@shearerline.com',
            'password' => Hash::make('password123'),
            'user_level' => 0,
        ]);
        $user->assignRole('user');

        // 为用户创建钱包
        $user->wallet()->create([
            'currency' => 'CNY',
            'balance' => 100000.00,
            'frozen_balance' => 0,
            'total_recharge' => 100000.00,
            'total_withdraw' => 0,
        ]);

        // 创建管理员用户
        $admin = User::create([
            'name' => '超级管理员',
            'email' => 'admin@shearerline.com',
            'password' => Hash::make('admin123'),
            'user_level' => 5,
        ]);
        $admin->assignRole('super_admin');

        $admin->wallet()->create([
            'currency' => 'CNY',
            'balance' => 500000.00,
            'frozen_balance' => 0,
            'total_recharge' => 500000.00,
            'total_withdraw' => 0,
        ]);

        // 创建财务人员
        $finance = User::create([
            'name' => '财务人员',
            'email' => 'finance@shearerline.com',
            'password' => Hash::make('finance123'),
            'user_level' => 0,
        ]);
        $finance->assignRole('finance');
    }
}
```

## 三、启动服务

### 3.1 启动后端服务

```bash
# 启动Laravel开发服务器
php artisan serve --host=0.0.0.0 --port=8000
```

### 3.2 启动队列处理器

```bash
# 启动队列监听（处理提现等异步任务）
php artisan queue:work redis --queue=withdraw,default --tries=3 --timeout=300

# 或者使用horizon（如果安装了）
php artisan horizon
```

### 3.3 启动定时任务调度器

```bash
# 在生产环境中添加到crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

# 本地开发可以手动运行
php artisan schedule:work
```

### 3.4 启动前端开发服务器

```bash
# 启动Vite开发服务器
npm run dev

# 或者构建生产版本
npm run build
```

## 四、测试账号

### 4.1 后台管理账号

| 角色 | 邮箱 | 密码 | 权限 |
|------|------|------|------|
| 超级管理员 | admin@shearerline.com | admin123 | 所有权限 |
| 财务人员 | finance@shearerline.com | finance123 | 审核提现、查看报表 |
| 普通用户 | test@shearerline.com | password123 | 申请提现、管理账户 |

### 4.2 测试数据

- 普通用户钱包余额：¥100,000.00
- 管理员钱包余额：¥500,000.00
- 已配置3种提现方式（银行转账、支付宝、微信）
- 已配置3条提现规则（不同用户等级）

## 五、测试用例

### 5.1 功能测试用例

#### 5.1.1 钱包模块测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 查看钱包余额 | 登录后访问 `/wallet/balance` | 显示可用余额、冻结余额、今日收支等数据 |
| 查看交易记录 | 访问 `/wallet/transactions` | 显示交易列表，支持筛选和分页 |
| 交易详情查看 | 点击某条交易的"详情"按钮 | 弹窗显示完整交易信息 |

#### 5.1.2 提现账户管理测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 添加银行卡账户 | 1. 访问 `/withdraw-accounts`<br>2. 点击"添加账户"<br>3. 选择银行转账，填写银行信息 | 账户创建成功，列表显示新账户 |
| 添加支付宝账户 | 1. 点击"添加账户"<br>2. 选择支付宝，填写账号和姓名 | 支付宝账户创建成功 |
| 设为默认账户 | 点击某个账户的"设为默认" | 该账户标记为默认，其他账户取消默认 |
| 编辑账户 | 点击编辑按钮，修改账户信息 | 账户信息更新成功 |
| 删除账户 | 点击删除按钮，确认删除 | 账户从列表中消失 |

#### 5.1.3 提现申请测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 正常提现申请 | 1. 访问 `/withdraw`<br>2. 选择提现账户<br>3. 输入提现金额 ¥1000<br>4. 点击提交 | 1. 提交成功<br>2. 钱包余额减少 ¥1000<br>3. 生成待审核的提现记录 |
| 提现金额不足 | 输入超过可用余额的金额 | 提示"提现金额不能超过可用余额" |
| 低于最低限额 | 输入 ¥50（低于规则的 ¥100） | 提交时提示金额低于最低限额 |
| 未选择账户 | 未添加账户时访问提现页面 | 提示添加账户，按钮禁用 |
| 手续费计算 | 输入 ¥1000，选择银行转账 | 自动计算手续费 ¥5，实际到账 ¥995 |

#### 5.1.4 提现审核测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 审核通过 | 1. 使用财务账号登录<br>2. 访问 `/withdraw/list`<br>3. 点击某条待审核记录的"通过" | 1. 状态变为"已审核"<br>2. 自动进入处理队列 |
| 审核拒绝 | 点击"拒绝"，填写拒绝原因 | 1. 状态变为"已拒绝"<br>2. 冻结金额退回钱包<br>3. 记录拒绝原因 |
| 批量审核 | 勾选多条记录，点击"批量通过" | 选中的记录状态批量更新 |
| 取消提现 | 用户在待审核状态下点击"取消" | 1. 状态变为"已取消"<br>2. 金额退回钱包 |

#### 5.1.5 提现规则管理测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 创建规则 | 1. 管理员访问 `/withdraw-config`<br>2. 点击"添加规则"<br>3. 填写规则信息 | 规则创建成功，列表显示 |
| 规则冲突检测 | 创建与现有规则同用户等级、同方式的规则 | 自动停用旧规则或提示冲突 |
| 启用/禁用规则 | 点击规则的状态开关 | 规则状态实时更新 |
| 规则优先级 | 创建多条规则，不同用户等级 | 申请提现时自动匹配最符合的规则 |

#### 5.1.6 提现方式管理测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 调整顺序 | 点击上下箭头调整提现方式顺序 | 顺序实时更新，前端显示顺序同步 |
| 启/禁用方式 | 禁用"微信支付" | 提现页面不再显示微信支付选项 |
| 修改手续费 | 将银行转账费率从0.5%改为0.3% | 新的提现申请按0.3%计算手续费 |

### 5.2 API接口测试

使用Postman或curl测试以下API：

#### 5.2.1 认证接口

```bash
# 登录获取Token
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@shearerline.com","password":"password123"}'
```

#### 5.2.2 钱包接口

```bash
# 获取余额
curl -X GET http://localhost:8000/api/wallet/balance \
  -H "Authorization: Bearer {token}"

# 获取交易记录
curl -X GET "http://localhost:8000/api/wallet/transactions?page=1&per_page=20" \
  -H "Authorization: Bearer {token}"
```

#### 5.2.3 提现接口

```bash
# 创建提现申请
curl -X POST http://localhost:8000/api/withdrawals \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 1000,
    "method": "bank_transfer",
    "account_id": 1,
    "remark": "测试提现"
  }'

# 获取提现列表
curl -X GET "http://localhost:8000/api/withdrawals?status=pending" \
  -H "Authorization: Bearer {token}"

# 审核通过（需要财务权限）
curl -X POST http://localhost:8000/api/withdrawals/1/approve \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"remark":"审核通过"}'
```

#### 5.2.4 提现账户接口

```bash
# 获取账户列表
curl -X GET http://localhost:8000/api/withdraw-accounts \
  -H "Authorization: Bearer {token}"

# 创建账户
curl -X POST http://localhost:8000/api/withdraw-accounts \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "bank_transfer",
    "withdraw_method_id": 1,
    "bank_name": "中国工商银行",
    "bank_account": "6222021234567890123",
    "account_name": "张三",
    "is_default": true
  }'
```

### 5.3 队列和定时任务测试

#### 5.3.1 队列任务测试

```bash
# 手动触发提现处理
php artisan tinker
>>> \App\Jobs\ProcessWithdrawRequestJob::dispatch(\App\Models\WithdrawRequest::find(1));

# 查看队列状态
php artisan queue:failed
php artisan queue:retry all
```

#### 5.3.2 定时任务测试

```bash
# 手动运行自动审核命令
php artisan withdraw:auto-audit

# 手动运行批量处理命令
php artisan withdraw:batch-process

# 手动运行每日对账命令
php artisan withdraw:daily-reconcile

# 查看调度列表
php artisan schedule:list
```

### 5.4 异常场景测试

| 测试场景 | 测试步骤 | 预期结果 |
|---------|---------|---------|
| 重复提交 | 快速点击两次提交按钮 | 只生成一笔提现记录 |
| 并发提现 | 两个请求同时提现，总金额超过余额 | 其中一笔失败，提示余额不足 |
| 接口未授权 | 不带Token访问需要认证的接口 | 返回401 Unauthorized |
| 权限不足 | 普通用户访问审核接口 | 返回403 Forbidden |
| 参数错误 | 创建提现时不传amount | 返回422验证错误，提示金额必填 |

## 六、模块架构说明

### 6.1 目录结构

```
backend/
├── app/
│   ├── Enums/                    # 枚举类
│   │   ├── WithdrawStatus.php
│   │   └── WithdrawAuditAction.php
│   ├── Exceptions/               # 自定义异常
│   │   ├── WithdrawException.php
│   │   ├── InsufficientBalanceException.php
│   │   ├── InvalidStatusException.php
│   │   └── ...
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       ├── WalletController.php
│   │   │       ├── WithdrawRequestController.php
│   │   │       ├── WithdrawRuleController.php
│   │   │       ├── WithdrawMethodController.php
│   │   │       └── UserWithdrawAccountController.php
│   │   └── Requests/             # 表单验证
│   │       └── Withdraw/
│   ├── Jobs/                     # 队列任务
│   │   ├── ProcessWithdrawRequestJob.php
│   │   └── BatchProcessWithdrawalsJob.php
│   ├── Console/
│   │   └── Commands/             # 定时任务命令
│   │       ├── AutoAuditWithdrawals.php
│   │       ├── BatchProcessWithdrawals.php
│   │       └── WithdrawDailyReconcile.php
│   ├── Models/
│   │   ├── Wallet.php
│   │   ├── WalletTransaction.php
│   │   ├── WithdrawRequest.php
│   │   ├── WithdrawAudit.php
│   │   ├── WithdrawRule.php
│   │   ├── WithdrawMethod.php
│   │   └── UserWithdrawAccount.php
│   └── Services/                 # 业务服务层
│       ├── WalletService.php
│       ├── WithdrawRequestService.php
│       └── WithdrawRuleService.php
├── database/
│   ├── migrations/                # 数据库迁移
│   └── seeders/
│       └── WithdrawModuleSeeder.php
├── resources/
│   ├── js/                        # Vue2 SPA前端
│   │   ├── views/
│   │   │   ├── wallet/
│   │   │   ├── withdraw/
│   │   │   ├── withdraw-accounts/
│   │   │   ├── withdraw-methods/
│   │   │   └── withdraw-rules/
│   │   └── api/
│   └── views/                     # Blade视图（混合模式）
│       ├── layouts/
│       ├── wallet/
│       ├── withdraw/
│       ├── withdraw-accounts/
│       ├── withdraw-methods/
│       └── withdraw-rules/
└── routes/
    ├── api.php                    # API路由
    ├── web.php                    # Web路由
    └── console.php                # 定时任务调度
```

### 6.2 核心业务流程

#### 6.2.1 提现申请流程

```
用户提交提现申请
    ↓
参数验证（金额范围、账户有效性）
    ↓
匹配适用的提现规则
    ↓
计算手续费
    ↓
检查钱包余额
    ↓
扣减余额（冻结）
    ↓
创建提现记录（状态：待审核）
    ↓
记录审计日志
    ↓
发送通知（可选）
```

#### 6.2.2 提现审核流程

```
财务人员审核
    ↓
验证状态合法性
    ↓
审核通过？
    ├─ 是 → 状态变为已审核 → 推送到处理队列
    └─ 否 → 状态变为已拒绝 → 解冻余额 → 记录拒绝原因
    ↓
记录审核日志
```

#### 6.2.3 提现处理流程

```
队列消费提现任务
    ↓
调用第三方支付接口
    ↓
支付成功？
    ├─ 是 → 状态变为已完成 → 更新实际打款时间
    └─ 否 → 状态变为已失败 → 解冻余额 → 记录失败原因
    ↓
记录处理日志
    ↓
发送到账通知
```

## 七、常见问题排查

### 7.1 数据库相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 迁移失败 | 表已存在或外键约束错误 | 检查迁移文件顺序，使用 `php artisan migrate:fresh`（注意会清空数据） |
| 连接超时 | 数据库配置错误 | 检查 `.env` 中的 `DB_HOST`、`DB_PORT` 配置 |

### 7.2 队列相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 任务不执行 | 队列未启动或Redis连接失败 | 启动 `queue:work`，检查Redis配置 |
| 任务执行失败 | 代码异常或第三方接口错误 | 查看 `storage/logs/laravel.log`，使用 `php artisan queue:failed` 查看失败任务 |

### 7.3 权限相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 403无权限 | 角色或权限未正确分配 | 检查用户角色，使用 `$user->getAllPermissions()` 验证权限 |
| 权限不生效 | 权限缓存问题 | 运行 `php artisan permission:cache-reset` 清除缓存 |

## 八、性能优化建议

1. **数据库层面**：为常用查询字段添加索引（user_id, status, created_at）
2. **缓存层面**：使用Redis缓存提现规则、用户余额等热点数据
3. **队列层面**：拆分不同优先级的队列，高优任务使用独立队列
4. **前端层面**：使用Vue异步组件、路由懒加载减少首屏加载时间
5. **接口层面**：对列表接口添加合理的分页，避免一次性加载大量数据

---

**文档版本**: v1.0  
**最后更新**: 2024年
