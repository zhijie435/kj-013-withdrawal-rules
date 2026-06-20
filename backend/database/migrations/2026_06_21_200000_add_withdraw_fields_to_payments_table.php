<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('transaction_no');
            $table->string('bank_account')->nullable()->after('bank_name');
            $table->string('account_name')->nullable()->after('bank_account');
            $table->string('alipay_account')->nullable()->after('account_name');
            $table->string('wechat_account')->nullable()->after('alipay_account');
            $table->unsignedBigInteger('audit_by')->nullable()->after('fail_reason');
            $table->timestamp('audit_at')->nullable()->after('audit_by');
            $table->text('audit_remark')->nullable()->after('audit_at');

            $table->index('audit_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'bank_name', 'bank_account', 'account_name',
                'alipay_account', 'wechat_account',
                'audit_by', 'audit_at', 'audit_remark',
            ]);
        });
    }
};
