<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawMethodController;
use App\Http\Controllers\Api\WithdrawRequestController;
use App\Http\Controllers\Api\WithdrawRuleController;
use App\Http\Controllers\Api\WithdrawalRuleController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\BankCardController;
use App\Http\Controllers\Api\UserWithdrawAccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\CustomsDeclarationController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\ShearerlineConfigController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductMarketPriceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShippingMethodController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxRuleController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::put('/password', [AuthController::class, 'changePassword'])->name('password.change');

    Route::apiResource('/users', UserController::class)->names('users');
    Route::put('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::get('/roles/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
    Route::apiResource('/roles', RoleController::class)->names('roles');

    Route::apiResource('/suppliers', SupplierController::class)->names('suppliers');
    Route::put('/suppliers/{supplier}/approve', [SupplierController::class, 'approve'])->name('suppliers.approve');
    Route::put('/suppliers/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('suppliers.toggle-status');

    Route::get('/distributors/tree', [DistributorController::class, 'tree'])->name('distributors.tree');
    Route::apiResource('/distributors', DistributorController::class)->names('distributors');
    Route::put('/distributors/{distributor}/approve', [DistributorController::class, 'approve'])->name('distributors.approve');
    Route::put('/distributors/{distributor}/toggle-status', [DistributorController::class, 'toggleStatus'])->name('distributors.toggle-status');

    Route::get('/categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
    Route::apiResource('/categories', CategoryController::class)->names('categories');

    Route::apiResource('/products', ProductController::class)->names('products');

    Route::apiResource('/orders', OrderController::class)->names('orders');
    Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('/orders/{order}/approve', [OrderController::class, 'approve'])->name('orders.approve');

    Route::apiResource('/payments', PaymentController::class)->names('payments');
    Route::post('/payments/{payment}/settle', [PaymentController::class, 'settle'])->name('payments.settle');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('/payments/{payment}/retry', [PaymentController::class, 'retry'])->name('payments.retry');
    Route::post('/payments/recharge', [PaymentController::class, 'recharge'])->name('payments.recharge');
    Route::get('/payments/balance/info', [PaymentController::class, 'balance'])->name('payments.balance');

    Route::apiResource('/inventory', InventoryController::class)->names('inventory');

    Route::apiResource('/markets', MarketController::class)->names('markets');
    Route::put('/markets/{market}/toggle-status', [MarketController::class, 'toggleStatus'])->name('markets.toggle-status');

    Route::apiResource('/warehouses', WarehouseController::class)->names('warehouses');
    Route::put('/warehouses/{warehouse}/toggle-status', [WarehouseController::class, 'toggleStatus'])->name('warehouses.toggle-status');

    Route::apiResource('/shipping-methods', ShippingMethodController::class)->names('shipping-methods');
    Route::post('/shipping-methods/{shippingMethod}/calculate', [ShippingMethodController::class, 'calculate'])->name('shipping-methods.calculate');

    Route::apiResource('/currency-rates', CurrencyRateController::class)->names('currency-rates');
    Route::get('/currency-rates/latest/pair', [CurrencyRateController::class, 'latest'])->name('currency-rates.latest');
    Route::post('/currency-rates/convert', [CurrencyRateController::class, 'convert'])->name('currency-rates.convert');

    Route::apiResource('/tax-rules', TaxRuleController::class)->names('tax-rules');
    Route::post('/tax-rules/calculate', [TaxRuleController::class, 'calculate'])->name('tax-rules.calculate');

    Route::apiResource('/product-market-prices', ProductMarketPriceController::class)->names('product-market-prices');

    Route::apiResource('/shipments', ShipmentController::class)->names('shipments');
    Route::put('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('shipments.update-status');

    Route::apiResource('/customs-declarations', CustomsDeclarationController::class)->names('customs-declarations');
    Route::put('/customs-declarations/{customsDeclaration}/status', [CustomsDeclarationController::class, 'updateStatus'])->name('customs-declarations.update-status');

    Route::get('/customer-groups/tree', [CustomerGroupController::class, 'tree'])->name('customer-groups.tree');
    Route::apiResource('/customer-groups', CustomerGroupController::class)->names('customer-groups');
    Route::put('/customer-groups/{customerGroup}/toggle-status', [CustomerGroupController::class, 'toggleStatus'])->name('customer-groups.toggle-status');
    Route::post('/customer-groups/{customerGroup}/attach-users', [CustomerGroupController::class, 'attachUsers'])->name('customer-groups.attach-users');
    Route::post('/customer-groups/{customerGroup}/detach-users', [CustomerGroupController::class, 'detachUsers'])->name('customer-groups.detach-users');
    Route::post('/customer-groups/{customerGroup}/attach-distributors', [CustomerGroupController::class, 'attachDistributors'])->name('customer-groups.attach-distributors');
    Route::post('/customer-groups/{customerGroup}/detach-distributors', [CustomerGroupController::class, 'detachDistributors'])->name('customer-groups.detach-distributors');

    Route::get('/config/withdraw', [ShearerlineConfigController::class, 'withdraw'])->name('config.withdraw');
    Route::put('/config/withdraw', [ShearerlineConfigController::class, 'updateWithdraw'])->name('config.withdraw.update');
    Route::get('/config/withdraw/public', [ShearerlineConfigController::class, 'publicWithdraw'])->name('config.withdraw.public');

    Route::post('/payments/withdraw', [PaymentController::class, 'withdraw'])->name('payments.withdraw');
    Route::get('/payments/withdraw/rules', [PaymentController::class, 'withdrawRules'])->name('payments.withdraw.rules');
    Route::get('/payments/withdraw/pending', [PaymentController::class, 'pendingWithdraws'])->name('payments.withdraw.pending');
    Route::get('/payments/withdraw/summary', [PaymentController::class, 'withdrawSummary'])->name('payments.withdraw.summary');
    Route::post('/payments/{payment}/approve-withdraw', [PaymentController::class, 'approveWithdraw'])->name('payments.withdraw.approve');
    Route::post('/payments/{payment}/reject-withdraw', [PaymentController::class, 'rejectWithdraw'])->name('payments.withdraw.reject');

    // ==================== 提现模块新路由 ====================

    // 钱包相关
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [WalletController::class, 'balance'])->name('wallet.balance');
        Route::get('/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');
        Route::get('/statistics', [WalletController::class, 'statistics'])->name('wallet.statistics');
        Route::get('/distributors/{distributor}/balance', [WalletController::class, 'distributorBalance'])->name('wallet.distributor.balance');
        Route::get('/distributors/{distributor}/transactions', [WalletController::class, 'distributorTransactions'])->name('wallet.distributor.transactions');
        Route::get('/distributors/{distributor}/statistics', [WalletController::class, 'distributorStatistics'])->name('wallet.distributor.statistics');
        Route::post('/distributors/{distributor}/adjust', [WalletController::class, 'adjustBalance'])->name('wallet.distributor.adjust');
        Route::post('/transfer', [WalletController::class, 'transfer'])->name('wallet.transfer');
    });

    // 提现方式
    Route::prefix('withdraw-methods')->group(function () {
        Route::get('/enabled', [WithdrawMethodController::class, 'enabled'])->name('withdraw-methods.enabled');
        Route::post('/{method}/toggle-status', [WithdrawMethodController::class, 'toggleStatus'])->name('withdraw-methods.toggle-status');
    });
    Route::apiResource('/withdraw-methods', WithdrawMethodController::class)->names('withdraw-methods');

    // 提现规则
    Route::prefix('withdraw-rules')->group(function () {
        Route::get('/enabled', [WithdrawRuleController::class, 'enabled'])->name('withdraw-rules.enabled');
        Route::get('/applicable', [WithdrawRuleController::class, 'applicable'])->name('withdraw-rules.applicable');
        Route::post('/{rule}/toggle-status', [WithdrawRuleController::class, 'toggleStatus'])->name('withdraw-rules.toggle-status');
    });
    Route::apiResource('/withdraw-rules', WithdrawRuleController::class)->names('withdraw-rules')->parameters(['withdraw-rules' => 'rule']);

    // 提现账户
    Route::prefix('withdraw-accounts')->group(function () {
        Route::post('/{account}/set-default', [UserWithdrawAccountController::class, 'setDefault'])->name('withdraw-accounts.set-default');
    });
    Route::apiResource('/withdraw-accounts', UserWithdrawAccountController::class)->names('withdraw-accounts')->parameters(['withdraw-accounts' => 'account']);

    // 提现申请
    Route::prefix('withdrawals')->group(function () {
        Route::get('/statistics', [WithdrawRequestController::class, 'statistics'])->name('withdrawals.statistics');
        Route::get('/pending-count', [WithdrawRequestController::class, 'pendingCount'])->name('withdrawals.pending-count');
        Route::post('/validate-amount', [WithdrawRequestController::class, 'validateAmount'])->name('withdrawals.validate-amount');
        Route::post('/batch-approve', [WithdrawRequestController::class, 'batchApprove'])->name('withdrawals.batch-approve');
        Route::post('/batch-reject', [WithdrawRequestController::class, 'batchReject'])->name('withdrawals.batch-reject');
        Route::post('/{withdraw}/approve', [WithdrawRequestController::class, 'approve'])->name('withdrawals.approve');
        Route::post('/{withdraw}/reject', [WithdrawRequestController::class, 'reject'])->name('withdrawals.reject');
        Route::post('/{withdraw}/cancel', [WithdrawRequestController::class, 'cancel'])->name('withdrawals.cancel');
        Route::post('/{withdraw}/process', [WithdrawRequestController::class, 'process'])->name('withdrawals.process');
        Route::post('/{withdraw}/complete', [WithdrawRequestController::class, 'complete'])->name('withdrawals.complete');
        Route::post('/{withdraw}/fail', [WithdrawRequestController::class, 'fail'])->name('withdrawals.fail');
    });
    Route::apiResource('/withdrawals', WithdrawRequestController::class)->names('withdrawals')->parameters(['withdrawals' => 'withdraw']);

    // ==================== 新版提现模块路由 ====================

    // 银行卡管理
    Route::prefix('bank-cards')->group(function () {
        Route::get('/all-active', [BankCardController::class, 'allActive'])->name('bank-cards.all-active');
        Route::get('/type-options', [BankCardController::class, 'getTypeOptions'])->name('bank-cards.type-options');
        Route::get('/bank-options', [BankCardController::class, 'getBankOptions'])->name('bank-cards.bank-options');
        Route::post('/{card}/set-default', [BankCardController::class, 'setDefault'])->name('bank-cards.set-default');
    });
    Route::apiResource('/bank-cards', BankCardController::class)->names('bank-cards')->parameters(['bank-cards' => 'card']);

    // 新版提现规则管理
    Route::prefix('withdrawal-rules')->group(function () {
        Route::get('/current', [WithdrawalRuleController::class, 'current'])->name('withdrawal-rules.current');
        Route::get('/status-options', [WithdrawalRuleController::class, 'getStatusOptions'])->name('withdrawal-rules.status-options');
        Route::get('/level-options', [WithdrawalRuleController::class, 'getLevelOptions'])->name('withdrawal-rules.level-options');
        Route::get('/method-options', [WithdrawalRuleController::class, 'getMethodOptions'])->name('withdrawal-rules.method-options');
        Route::get('/currency-options', [WithdrawalRuleController::class, 'getCurrencyOptions'])->name('withdrawal-rules.currency-options');
        Route::post('/{rule}/toggle-active', [WithdrawalRuleController::class, 'toggleActive'])->name('withdrawal-rules.toggle-active');
    });
    Route::apiResource('/withdrawal-rules', WithdrawalRuleController::class)->names('withdrawal-rules')->parameters(['withdrawal-rules' => 'rule']);

    // 新版提现申请管理
    Route::prefix('withdrawal-v2')->group(function () {
        Route::get('/statistics', [WithdrawalController::class, 'statistics'])->name('withdrawal-v2.statistics');
        Route::get('/status-options', [WithdrawalController::class, 'getStatusOptions'])->name('withdrawal-v2.status-options');
        Route::post('/calculate-fee', [WithdrawalController::class, 'calculateFee'])->name('withdrawal-v2.calculate-fee');
        Route::post('/apply', [WithdrawalController::class, 'apply'])->name('withdrawal-v2.apply');
        Route::post('/batch-approve', [WithdrawalController::class, 'batchApprove'])->name('withdrawal-v2.batch-approve');
        Route::post('/batch-process', [WithdrawalController::class, 'batchProcess'])->name('withdrawal-v2.batch-process');
        Route::post('/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawal-v2.approve');
        Route::post('/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawal-v2.reject');
        Route::post('/{withdrawal}/process', [WithdrawalController::class, 'process'])->name('withdrawal-v2.process');
        Route::post('/{withdrawal}/complete', [WithdrawalController::class, 'complete'])->name('withdrawal-v2.complete');
        Route::post('/{withdrawal}/fail', [WithdrawalController::class, 'fail'])->name('withdrawal-v2.fail');
        Route::post('/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('withdrawal-v2.cancel');
    });
    Route::apiResource('/withdrawal-v2', WithdrawalController::class)->names('withdrawal-v2')->parameters(['withdrawal-v2' => 'withdrawal']);
});
