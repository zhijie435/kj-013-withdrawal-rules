<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('distributor_id')->nullable();
            $table->unsignedBigInteger('withdraw_method_id');
            $table->unsignedBigInteger('withdraw_rule_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('actual_amount', 15, 2);
            $table->string('currency', 10)->default('CNY');
            $table->json('account_info')->nullable();
            $table->text('remark')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->timestamp('audit_time')->nullable();
            $table->text('audit_remark')->nullable();
            $table->string('transaction_no', 100)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('distributor_id');
            $table->index('withdraw_method_id');
            $table->index('status');
            $table->index(['status', 'created_at']);
            $table->index('created_at');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('distributor_id')
                ->references('id')
                ->on('distributors')
                ->onDelete('set null');

            $table->foreign('withdraw_method_id')
                ->references('id')
                ->on('withdraw_methods')
                ->onDelete('restrict');

            $table->foreign('withdraw_rule_id')
                ->references('id')
                ->on('withdraw_rules')
                ->onDelete('set null');

            $table->foreign('auditor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
