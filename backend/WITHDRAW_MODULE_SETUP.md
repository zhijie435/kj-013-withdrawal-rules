# Shearerline 提现规则模块 - 部署文档

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

---

## 二、环境变量配置

### 2.1 基础环境配置

编辑 `.env` 文件，配置以下基础项：

```dotenv
APP_NAME="Shearerline提现系统"
APP_ENV=local
APP_KEY=base64:CHANGE_THIS_RANDOM_STRING_32_CHARS
APP_DEBUG=true
APP_TIMEZONE=Asia/Shanghai
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shearerline_withdrawal
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
QUEUE_DRIVER=redis

CACHE_DRIVER=redis
SESSION_DRIVER=file
SESSION_LIFETIME=120

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,localhost:8080
```

### 2.2 提现模块专属环境变量

```dotenv
# ========== 提现费率及限额配置 ==========
WITHDRAWAL_FEE_RATE=0.005            # 默认提现手续费率（0.5%）
WITHDRAWAL_MIN_AMOUNT=100            # 单笔最低提现金额（元）
WITHDRAWAL_MAX_AMOUNT=50000          # 单笔最高提现金额（元）
WITHDRAWAL_DAILY_LIMIT=200000        # 每日提现总限额（元）
WITHDRAWAL_SETTLEMENT_DAYS=7         # 默认结算周期（天）

# ========== 队列配置 ==========
WITHDRAWAL_QUEUE_CONNECTION=redis    # 队列连接驱动
WITHDRAWAL_QUEUE_NAME=withdrawals    # 提现专属队列名称

# ========== 自动审核配置 ==========
WITHDRAWAL_AUTO_AUDIT_ENABLED=true   # 是否启用自动审核
WITHDRAWAL_AUTO_AUDIT_LIMIT=50       # 每次自动审核处理的最大笔数

# ========== 批量处理配置 ==========
WITHDRAWAL_BATCH_PROCESS_ENABLED=true  # 是否启用批量处理
WITHDRAWAL_BATCH_PROCESS_SIZE=50       # 每批处理的提现笔数

# ========== 其他运营配置 ==========
WITHDRAWAL_AUTO_SETTLE_DAYS=0        # 自动结算等待天数（0表示立即）
WITHDRAWAL_NOTIFY_PENDING_HOURS=1    # 待审核超时通知阈值（小时）
WITHDRAWAL_EXPIRED_CLEAN_DAYS=30     # 过期提现记录保留天数
```

### 2.3 环境变量说明

| 变量名 | 默认值 | 说明 |
|--------|--------|------|
| WITHDRAWAL_FEE_RATE | 0.005 | 默认手续费率，适用于未配置规则的场景 |
| WITHDRAWAL_MIN_AMOUNT | 100 | 单笔最低提现金额 |
| WITHDRAWAL_MAX_AMOUNT | 50000 | 单笔最高提现金额 |
| WITHDRAWAL_DAILY_LIMIT | 200000 | 用户每日提现总额上限 |
| WITHDRAWAL_SETTLEMENT_DAYS | 7 | 默认结算周期 |
| WITHDRAWAL_QUEUE_CONNECTION | redis | 队列驱动，建议使用redis |
| WITHDRAWAL_QUEUE_NAME | withdrawals | 提现任务队列名称 |
| WITHDRAWAL_AUTO_AUDIT_ENABLED | true | SVIP等免审核用户是否自动通过 |
| WITHDRAWAL_AUTO_AUDIT_LIMIT | 50 | 单次定时任务自动审核的最大条数 |
| WITHDRAWAL_BATCH_PROCESS_ENABLED | true | 是否启用定时批量打款 |
| WITHDRAWAL_BATCH_PROCESS_SIZE | 50 | 批量处理每次处理的笔数 |
| WITHDRAWAL_AUTO_SETTLE_DAYS | 0 | 完成后多少天自动结算 |
| WITHDRAWAL_NOTIFY_PENDING_HOURS | 1 | 超过多少小时未审核则通知财务 |
| WITHDRAWAL_EXPIRED_CLEAN_DAYS | 30 | 已取消/失败的记录保留天数 |

---

## 三、安装步骤

### 3.1 克隆项目并安装依赖

```bash
cd backend

composer install

npm install
```

### 3.2 环境配置

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`，按照第二章配置数据库、Redis和提现参数。

### 3.3 创建数据库

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS shearerline_withdrawal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 四、数据库迁移

提现模块涉及以下迁移文件，按顺序执行：

### 4.1 迁移文件清单

| 序号 | 迁移文件 | 说明 |
|------|----------|------|
| 1 | `2026_06_19_000001_create_wallets_table.php` | 钱包表 |
| 2 | `2026_06_19_000002_create_wallet_transactions_table.php` | 钱包交易记录表 |
| 3 | `2026_06_19_000003_add_level_to_users_table.php` | 用户等级字段 |
| 4 | `2026_06_21_200000_add_withdraw_fields_to_payments_table.php` | 支付表增加提现字段 |
| 5 | `2026_06_22_000001_create_withdraw_methods_table.php` | 提现方式表（旧版） |
| 6 | `2026_06_22_000002_create_withdraw_rules_table.php` | 提现规则表（旧版） |
| 7 | `2026_06_22_000003_create_withdraw_requests_table.php` | 提现申请表（旧版） |
| 8 | `2026_06_22_000004_create_withdraw_audits_table.php` | 提现审核日志表 |
| 9 | `2026_06_22_000005_create_user_withdraw_accounts_table.php` | 用户提现账户表 |
| 10 | `2026_06_22_000006_create_bank_cards_table.php` | 银行卡表（新版） |
| 11 | `2026_06_22_000007_create_withdrawal_rules_table.php` | 提现规则表（新版） |
| 12 | `2026_06_22_000008_create_withdrawals_table.php` | 提现记录表（新版） |

### 4.2 执行迁移

```bash
php artisan migrate
```

如需重置（**慎用，会清空所有数据**）：

```bash
php artisan migrate:fresh
```

---

## 五、数据库种子（Seeder）

### 5.1 种子文件清单

| 种子类 | 说明 |
|--------|------|
| `DatabaseSeeder` | 总入口，调用以下所有Seeder |
| `PermissionSeeder` | 权限和角色初始化 |
| `ShearerlineConfigSeeder` | 系统配置初始化 |
| `WithdrawModuleSeeder` | 提现模块专属数据 |

### 5.2 WithdrawModuleSeeder 填充内容

`WithdrawModuleSeeder` 会自动创建以下数据：

#### 5.2.1 权限列表

| 权限标识 | 说明 |
|----------|------|
| withdraw.view | 查看提现记录 |
| withdraw.create | 申请提现 |
| withdraw.approve | 审核通过 |
| withdraw.reject | 审核拒绝 |
| withdraw.cancel | 取消提现 |
| withdraw.process | 处理打款 |
| withdraw-rule.view | 查看提现规则 |
| withdraw-rule.manage | 管理提现规则 |
| withdraw-method.view | 查看提现方式 |
| withdraw-method.manage | 管理提现方式 |
| withdraw-account.view | 查看提现账户 |
| withdraw-account.manage | 管理提现账户 |
| wallet.view | 查看钱包 |
| wallet.manage | 管理钱包 |
| user.manage | 管理用户 |

#### 5.2.2 角色及权限分配

| 角色 | 分配的权限 |
|------|-----------|
| super_admin（超级管理员） | 所有权限 |
| finance（财务人员） | withdraw.view, withdraw.approve, withdraw.reject, withdraw.process, withdraw-rule.view, withdraw-method.view, wallet.view, withdraw-account.view |
| operation（运营人员） | withdraw.view, withdraw-rule.view, withdraw-rule.manage, withdraw-method.view, withdraw-method.manage, wallet.view |
| user（普通用户） | withdraw.view, withdraw.create, withdraw.cancel, withdraw-account.view, withdraw-account.manage, wallet.view |

#### 5.2.3 默认提现方式

| code | 名称 | 手续费率 | 单笔范围 | 日限额 |
|------|------|---------|---------|--------|
| bank_transfer | 银行转账 | 0.5% | ¥100 - ¥500,000 | ¥500,000 |
| alipay | 支付宝 | 0.3% | ¥10 - ¥50,000 | ¥50,000 |
| wechat | 微信支付 | 0.3% | ¥10 - ¥50,000 | ¥50,000 |

#### 5.2.4 默认提现规则

| 规则名称 | 用户等级 | 提现方式 | 手续费率 | 单笔范围 | 日限额 | 免审核 |
|---------|---------|---------|---------|---------|--------|--------|
| 普通用户提现规则 | normal | bank_transfer | 0.5% | ¥100-¥50,000 | ¥50,000 | 否 |
| VIP用户提现规则 | vip | bank_transfer | 0.3% | ¥100-¥100,000 | ¥100,000 | 否 |
| SVIP用户免手续费规则 | svip | bank_transfer | 0% | ¥100-¥500,000 | ¥500,000 | 是 |
| 普通用户支付宝提现 | normal | alipay | 0.3% | ¥10-¥50,000 | ¥50,000 | 否 |
| 普通用户微信提现 | normal | wechat | 0.3% | ¥10-¥50,000 | ¥50,000 | 否 |

#### 5.2.5 默认测试账号

| 角色 | 邮箱 | 密码 | 用户等级 | 钱包余额 |
|------|------|------|---------|---------|
| 超级管理员 | admin@shearerline.com | admin123 | svip | ¥500,000.00 |
| 财务人员 | finance@shearerline.com | finance123 | normal | - |
| 普通用户 | test@shearerline.com | password123 | normal | ¥100,000.00 |

### 5.3 执行种子

```bash
php artisan db:seed
```

或单独执行提现模块种子：

```bash
php artisan db:seed --class=WithdrawModuleSeeder
```

---

## 六、队列任务配置

### 6.1 队列任务（Jobs）清单

| Job类 | 说明 | 重试次数 | 队列 |
|--------|------|---------|------|
| `ProcessWithdrawRequestJob` | 处理单条提现申请（旧版），支持process/complete/auto_audit三种动作 | 3 | withdrawals |
| `BatchProcessWithdrawalsJob` | 批量分发已审核通过的提现申请到处理队列 | 1 | withdrawals |
| `ProcessWithdrawalJob` | 处理单条提现（新版），包含process和complete | - | withdrawals |
| `WithdrawNotificationJob` | 提现状态变更通知（提交/通过/拒绝） | - | default |

### 6.2 队列启动命令

#### 6.2.1 本地开发环境

```bash
php artisan queue:work redis --queue=withdrawals,default --tries=3 --timeout=300
```

参数说明：
- `redis`：使用Redis作为队列驱动
- `--queue=withdrawals,default`：按优先级消费提现队列和默认队列
- `--tries=3`：失败最多重试3次
- `--timeout=300`：单个任务最长执行300秒

#### 6.2.2 生产环境（Supervisor配置示例）

```ini
[program:laravel-queue-withdrawals]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/backend/artisan queue:work redis --queue=withdrawals,default --tries=3 --timeout=300 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/backend/storage/logs/queue-withdrawals.log
stopwaitsecs=3600
```

启动Supervisor：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-queue-withdrawals:*
```

### 6.3 队列常用运维命令

```bash
php artisan queue:failed                    # 查看失败任务
php artisan queue:retry all                 # 重试所有失败任务
php artisan queue:retry 1                   # 重试指定ID的失败任务
php artisan queue:flush                     # 清空所有失败任务
php artisan queue:restart                   # 重启队列工作进程（部署后必执行）
php artisan queue:listen                    # 监听队列（开发用，代码变更自动生效）
```

---

## 七、定时任务（Schedule）配置

### 7.1 定时任务清单

| 命令 | 频率 | 执行时间 | 说明 |
|------|------|---------|------|
| `withdraw:auto-audit` | 每10分钟 | - | 自动审核免审核规则的提现申请 |
| `withdraw:batch-process` | 每日一次 | 10:00 | 批量处理已审核通过的提现申请 |
| `withdraw:daily-reconcile` | 每日一次 | 02:00 | 昨日提现对账统计 |
| `withdrawal:reset-daily` | 每日一次 | 00:00 | 重置用户每日提现金额统计 |
| `withdrawal:auto-settle` | 每日一次 | 03:00 | 自动结算已完成的提现订单 |
| `withdrawal:notify-pending` | 每小时 | - | 通知财务处理超时未审核的提现申请 |
| `withdrawal:clean-expired` | 每周一次 | 周日 04:00 | 清理30天前已取消/失败的软删除记录 |

### 7.2 启动调度器

#### 7.2.1 生产环境（Crontab）

```bash
crontab -e
```

添加以下内容：

```cron
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

#### 7.2.2 本地开发环境

```bash
php artisan schedule:work
```

### 7.3 查看调度列表

```bash
php artisan schedule:list
```

---

## 八、Artisan 命令清单

### 8.1 提现处理命令

| 命令 | 参数 | 说明 |
|------|------|------|
| `php artisan withdraw:auto-audit` | `--limit=50` | 自动审核符合条件的提现申请 |
| `php artisan withdraw:batch-process` | `--batch-size=50` | 批量处理已审核通过的提现 |
| `php artisan withdraw:daily-reconcile` | `--date=YYYY-MM-DD` | 指定日期的提现对账统计 |
| `php artisan withdrawal:reset-daily` | - | 重置钱包每日提现统计 |
| `php artisan withdrawal:auto-settle` | - | 自动结算已完成订单 |
| `php artisan withdrawal:notify-pending` | - | 通知处理超时待审核订单 |
| `php artisan withdrawal:clean-expired` | - | 清理过期的已取消/失败记录 |

### 8.2 命令使用示例

```bash
# 自动审核100条
php artisan withdraw:auto-audit --limit=100

# 批量处理200条
php artisan withdraw:batch-process --batch-size=200

# 对账指定日期（2026-06-20）
php artisan withdraw:daily-reconcile --date=2026-06-20
```

---

## 九、验收命令及验证步骤

### 9.1 基础环境验收

```bash
# 1. PHP版本检查
php -v   # 应输出 PHP >= 8.2

# 2. PHP扩展检查
php -m | grep -E "redis|pdo_mysql|mbstring|openssl"

# 3. Laravel版本
php artisan --version   # 应输出 Laravel Framework 11.x

# 4. Composer依赖完整性
composer install --dry-run

# 5. 环境配置检查
php artisan env          # 确认当前环境
php artisan key:generate --dry-run  # 检查APP_KEY
```

### 9.2 数据库验收

```bash
# 1. 数据库连接测试
php artisan db:show

# 2. 迁移状态检查
php artisan migrate:status

# 3. 迁移执行
php artisan migrate --force

# 4. 验证提现相关表是否存在
php artisan tinker <<'EOF'
$tables = ['wallets', 'wallet_transactions', 'withdraw_methods', 'withdraw_rules',
           'withdraw_requests', 'withdraw_audits', 'user_withdraw_accounts',
           'bank_cards', 'withdrawal_rules', 'withdrawals'];
foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo "$table: " . ($exists ? 'OK' : 'MISSING') . PHP_EOL;
}
EOF
```

### 9.3 Redis和队列验收

```bash
# 1. Redis连接测试
php artisan tinker <<'EOF'
try {
    Redis::ping();
    echo "Redis连接: OK" . PHP_EOL;
} catch (\Exception $e) {
    echo "Redis连接失败: " . $e->getMessage() . PHP_EOL;
}
EOF

# 2. 队列连接测试
php artisan tinker <<'EOF'
try {
    $connection = Queue::connection(config('withdrawal.queue.connection'));
    echo "队列连接: OK" . PHP_EOL;
    echo "队列驱动: " . get_class($connection) . PHP_EOL;
} catch (\Exception $e) {
    echo "队列连接失败: " . $e->getMessage() . PHP_EOL;
}
EOF

# 3. 推送测试任务
php artisan tinker <<'EOF'
use App\Jobs\ProcessWithdrawRequestJob;
use App\Models\WithdrawRequest;

$withdraw = WithdrawRequest::first();
if ($withdraw) {
    ProcessWithdrawRequestJob::dispatch($withdraw, 'auto_audit')
        ->onQueue(config('withdrawal.queue.name'));
    echo "测试任务已推送到队列" . PHP_EOL;
} else {
    echo "暂无提现记录，请先执行seeder" . PHP_EOL;
}
EOF
```

### 9.4 种子数据验收

```bash
# 1. 执行全部种子
php artisan db:seed --force

# 2. 验证角色和权限
php artisan tinker <<'EOF'
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

echo "角色总数: " . Role::count() . PHP_EOL;
echo "权限总数: " . Permission::count() . PHP_EOL;
foreach (Role::all() as $role) {
    echo "角色 [{$role->name}] 权限数: " . $role->permissions->count() . PHP_EOL;
}
EOF

# 3. 验证提现方式
php artisan tinker <<'EOF'
use App\Models\WithdrawMethod;

echo "提现方式数: " . WithdrawMethod::count() . PHP_EOL;
WithdrawMethod::all(['code', 'name', 'status'])->each(function ($m) {
    echo "  - {$m->code}: {$m->name} (" . ($m->status ? '启用' : '禁用') . ")" . PHP_EOL;
});
EOF

# 4. 验证提现规则
php artisan tinker <<'EOF'
use App\Models\WithdrawRule;

echo "提现规则数: " . WithdrawRule::count() . PHP_EOL;
WithdrawRule::all(['name', 'user_level', 'min_amount', 'max_amount', 'fee_rate'])->each(function ($r) {
    echo "  - [{$r->user_level}] {$r->name}: ¥{$r->min_amount}-¥{$r->max_amount} 费率" . ($r->fee_rate * 100) . "%" . PHP_EOL;
});
EOF

# 5. 验证测试用户
php artisan tinker <<'EOF'
use App\Models\User;

$users = User::whereIn('email', ['admin@shearerline.com', 'finance@shearerline.com', 'test@shearerline.com'])->get();
foreach ($users as $user) {
    $wallet = $user->wallets->first();
    $roles = $user->getRoleNames()->implode(', ');
    echo "用户 [{$user->email}] 角色: {$roles}, 钱包余额: " . ($wallet ? "¥{$wallet->balance}" : '无') . PHP_EOL;
}
EOF
```

### 9.5 API接口验收

使用以下curl命令验证API是否正常：

```bash
# 1. 用户登录获取Token
ADMIN_TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@shearerline.com","password":"admin123"}' \
  | jq -r '.data.token')
echo "管理员Token: $ADMIN_TOKEN"

USER_TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@shearerline.com","password":"password123"}' \
  | jq -r '.data.token')
echo "普通用户Token: $USER_TOKEN"

# 2. 获取钱包余额（用户）
curl -s http://localhost:8000/api/wallet/balance \
  -H "Authorization: Bearer $USER_TOKEN" | jq

# 3. 获取提现方式列表
curl -s http://localhost:8000/api/withdraw-methods/enabled \
  -H "Authorization: Bearer $USER_TOKEN" | jq

# 4. 获取适用的提现规则
curl -s http://localhost:8000/api/withdraw-rules/applicable \
  -H "Authorization: Bearer $USER_TOKEN" | jq

# 5. 管理员获取提现统计
curl -s http://localhost:8000/api/withdrawals/statistics \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq
```

### 9.6 队列和定时任务验收

```bash
# 1. 查看定时任务列表
php artisan schedule:list

# 2. 手动执行自动审核
php artisan withdraw:auto-audit -v

# 3. 手动执行批量处理
php artisan withdraw:batch-process -v

# 4. 手动执行每日对账
php artisan withdraw:daily-reconcile -v

# 5. 重置每日统计
php artisan withdrawal:reset-daily -v

# 6. 自动结算
php artisan withdrawal:auto-settle -v

# 7. 待审核通知检查
php artisan withdrawal:notify-pending -v

# 8. 清理过期记录
php artisan withdrawal:clean-expired -v

# 9. 检查队列失败任务
php artisan queue:failed
```

### 9.7 权限验收

```bash
php artisan tinker <<'EOF'
use App\Models\User;

$admin = User::where('email', 'admin@shearerline.com')->first();
$finance = User::where('email', 'finance@shearerline.com')->first();
$user = User::where('email', 'test@shearerline.com')->first();

echo "=== 超级管理员权限 ===" . PHP_EOL;
echo "withdraw.approve: " . ($admin->can('withdraw.approve') ? 'YES' : 'NO') . PHP_EOL;
echo "withdraw-rule.manage: " . ($admin->can('withdraw-rule.manage') ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "=== 财务人员权限 ===" . PHP_EOL;
echo "withdraw.approve: " . ($finance->can('withdraw.approve') ? 'YES' : 'NO') . PHP_EOL;
echo "withdraw-rule.manage: " . ($finance->can('withdraw-rule.manage') ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "=== 普通用户权限 ===" . PHP_EOL;
echo "withdraw.create: " . ($user->can('withdraw.create') ? 'YES' : 'NO') . PHP_EOL;
echo "withdraw.approve: " . ($user->can('withdraw.approve') ? 'YES' : 'NO') . PHP_EOL;
EOF
```

---

## 十、模块架构说明

### 10.1 目录结构

```
backend/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── AutoAuditWithdrawals.php          # 自动审核命令
│   │       ├── BatchProcessWithdrawals.php        # 批量处理命令
│   │       ├── WithdrawDailyReconcile.php         # 每日对账命令
│   │       ├── ResetDailyWithdrawn.php            # 重置每日统计
│   │       ├── CleanExpiredWithdrawals.php        # 清理过期记录
│   │       ├── NotifyPendingWithdrawals.php       # 待审核通知
│   │       └── AutoSettleWithdrawals.php          # 自动结算
│   ├── Enums/
│   │   ├── WithdrawStatus.php                     # 提现状态枚举
│   │   └── WithdrawAuditAction.php                # 审核动作枚举
│   ├── Exceptions/
│   │   ├── WithdrawException.php
│   │   └── WithdrawalRule/                        # 规则相关异常
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── WalletController.php
│   │   │   ├── WithdrawMethodController.php
│   │   │   ├── WithdrawRuleController.php
│   │   │   ├── WithdrawRequestController.php
│   │   │   ├── WithdrawalRuleController.php       # 新版规则
│   │   │   ├── WithdrawalController.php           # 新版提现
│   │   │   ├── BankCardController.php             # 银行卡
│   │   │   └── UserWithdrawAccountController.php
│   │   └── Requests/
│   │       ├── WithdrawRequest.php
│   │       └── WithdrawConfigRequest.php
│   ├── Jobs/
│   │   ├── ProcessWithdrawRequestJob.php          # 处理提现（旧版）
│   │   ├── BatchProcessWithdrawalsJob.php         # 批量处理
│   │   ├── ProcessWithdrawalJob.php               # 处理提现（新版）
│   │   └── WithdrawNotificationJob.php            # 状态通知
│   ├── Models/
│   │   ├── Wallet.php
│   │   ├── WalletTransaction.php
│   │   ├── WithdrawMethod.php
│   │   ├── WithdrawRule.php
│   │   ├── WithdrawRequest.php
│   │   ├── WithdrawAudit.php
│   │   ├── WithdrawalRule.php                     # 新版规则模型
│   │   ├── Withdrawal.php                         # 新版提现模型
│   │   ├── UserWithdrawAccount.php
│   │   └── WithdrawMethod.php
│   ├── Policies/
│   │   └── WithdrawalRulePolicy.php
│   ├── Providers/
│   │   └── WithdrawalServiceProvider.php
│   ├── Repositories/
│   │   └── WithdrawalRuleRepository.php
│   └── Services/
│       ├── WalletService.php
│       ├── WithdrawRequestService.php
│       ├── WithdrawRuleService.php
│       ├── WithdrawConfigService.php
│       ├── WithdrawalRuleService.php              # 新版规则服务
│       ├── WithdrawalService.php                  # 新版提现服务
│       └── StateMachine/
│           └── WithdrawalStateMachine.php         # 提现状态机
├── config/
│   └── withdrawal.php                              # 提现模块配置
├── database/
│   ├── factories/
│   │   ├── WalletFactory.php
│   │   ├── WithdrawalFactory.php
│   │   └── WithdrawalRuleFactory.php
│   ├── migrations/                                 # 见第四章
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── WithdrawModuleSeeder.php
├── routes/
│   ├── api.php                                     # API路由
│   └── console.php                                 # 定时任务调度
└── resources/
    └── js/
        ├── api/
        │   ├── withdrawal.js
        │   ├── withdrawalRule.js
        │   ├── withdraw.js
        │   ├── withdrawRule.js
        │   ├── withdrawAccount.js
        │   └── withdrawMethod.js
        └── views/
            ├── wallet/
            ├── withdraw/
            ├── withdraw-config/
            ├── withdraw-methods/
            ├── withdraw-rules/
            ├── withdrawal-rules/
            └── withdraw-accounts/
```

### 10.2 核心业务流程

#### 10.2.1 提现申请流程

```
用户提交提现申请
    ↓
参数验证（金额范围、账户有效性）
    ↓
匹配适用的提现规则（按用户等级+提现方式）
    ↓
计算手续费（费率×金额，夹在min_fee和max_fee之间）
    ↓
检查钱包余额（可用余额 >= 申请金额 + 手续费）
    ↓
扣减余额（冻结：wallet.balance -= amount，wallet.frozen_amount += amount）
    ↓
创建提现记录（状态：pending 待审核）
    ↓
记录审计日志 withdraw_audits
    ↓
发送通知（WithdrawNotificationJob）
    ↓
规则免审核？
    ├─ 是 → 自动审核（ProcessWithdrawRequestJob action=auto_audit）
    └─ 否 → 等待财务人工审核
```

#### 10.2.2 提现审核流程

```
财务人员审核
    ↓
验证当前状态是否为 pending
    ↓
审核结果？
    ├─ 通过（approve）
    │     → 状态变为 approved
    │     → 记录审核日志
    │     → 推送到处理队列（ProcessWithdrawRequestJob action=process）
    │
    └─ 拒绝（reject）
          → 状态变为 rejected
          → 解冻余额（wallet.frozen_amount -= amount，wallet.balance += amount）
          → 记录拒绝原因 audit_remark
          → 发送拒绝通知
```

#### 10.2.3 提现处理（打款）流程

```
队列消费 ProcessWithdrawRequestJob / ProcessWithdrawalJob
    ↓
验证状态为 approved
    ↓
状态变为 processing
    ↓
调用第三方支付接口（银行/支付宝/微信）
    ↓
支付结果？
    ├─ 成功
    │     → 状态变为 completed
    │     → 记录交易号 transaction_id / third_party_no
    │     → 更新 processed_at / completed_at
    │     → 扣减冻结金额 wallet.frozen_amount -= amount
    │     → 累计 total_withdrawn += actual_amount
    │     → 发送到账通知
    │
    └─ 失败
          → 状态变为 failed
          → 解冻余额退回钱包
          → 记录失败原因 fail_reason
          → 发送失败通知
```

---

## 十一、服务启动

### 11.1 启动后端服务

```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

### 11.2 启动队列处理器

```bash
# 开发环境
php artisan queue:work redis --queue=withdrawals,default --tries=3 --timeout=300

# 生产环境（使用Supervisor，见6.2.2节）
```

### 11.3 启动定时任务调度器

```bash
# 开发环境
php artisan schedule:work

# 生产环境
# 已配置在crontab中，见第七章
```

### 11.4 启动前端开发服务器

```bash
cd backend
npm run dev

# 或构建生产版本
npm run build
```

---

## 十二、常见问题排查

### 12.1 数据库相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 迁移失败：外键约束错误 | 迁移执行顺序错误或关联表不存在 | 检查迁移文件名的日期前缀，使用 `php artisan migrate:fresh`（开发环境） |
| 迁移失败：表已存在 | 之前执行过但未记录 | `php artisan migrate:rollback` 或删除数据库重建 |
| 连接超时 | DB_HOST/DB_PORT 配置错误 | 检查 `.env`，确认MySQL服务运行 `mysqladmin ping` |
| Seeder重复插入报错 | 已执行过种子 | 代码中已使用 firstOrCreate，可直接重复执行 |

### 12.2 队列相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 任务不执行 | 队列未启动或Redis连接失败 | 启动 `queue:work`，检查Redis `redis-cli ping` |
| 任务执行失败 | 代码异常或第三方接口错误 | 查看日志 `tail -f storage/logs/laravel.log`，查看失败任务 `php artisan queue:failed` |
| 代码变更不生效 | queue:work是常驻进程 | 每次部署后执行 `php artisan queue:restart` |
| 任务不重试 | tries配置为0或已达到最大次数 | 检查Job类的 `$tries` 属性，手动重试 `php artisan queue:retry <id>` |

### 12.3 权限相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 403无权限 | 角色或权限未正确分配 | 检查用户角色，验证权限 `$user->getAllPermissions()` |
| 权限不生效 | 权限缓存 | `php artisan permission:cache-reset` |
| 登录后401 | Token过期或无效 | 重新登录获取新Token |

### 12.4 提现业务相关

| 问题 | 可能原因 | 解决方案 |
|------|---------|---------|
| 找不到匹配的提现规则 | 用户等级或提现方式未配置规则 | 后台添加规则 `withdraw-rules` |
| 余额不足但实际有钱 | 冻结金额占用 | 查看钱包 `wallet.balance` 和 `wallet.frozen_amount` |
| 自动审核不生效 | 规则 requires_audit = true | 检查规则配置，SVIP规则应为 false |
| 手续费计算异常 | fee_rate/min_fee/max_fee 配置 | 检查规则或config/withdrawal.php |

---

## 十三、性能优化建议

1. **数据库层面**：迁移中已为常用查询字段添加索引（user_id, status, created_at, request_no 等）
2. **缓存层面**：使用Redis缓存提现规则、用户余额等热点数据
3. **队列层面**：提现任务使用独立 `withdrawals` 队列，避免与其他任务互相阻塞
4. **前端层面**：使用Vue异步组件、路由懒加载减少首屏加载时间
5. **接口层面**：列表接口均支持分页（page/per_page），避免一次性加载大量数据

---

**文档版本**: v2.0
**最后更新**: 2026-06-21
