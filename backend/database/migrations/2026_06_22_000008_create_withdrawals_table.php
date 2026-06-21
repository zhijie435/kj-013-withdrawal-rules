<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('withdrawal_no', 50)->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->unsignedBigInteger('bank_card_id');
            $table->string('currency', 10)->default('CNY');
            $table->string('withdrawal_method', 50)->default('bank_transfer');
            $table->decimal('request_amount', 15, 2);
            $table->decimal('fee_rate', 7, 4)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2);
            $table->string('status', 30)->default('pending');
            $table->text('reject_reason')->nullable();
            $table->text('fail_reason')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('third_party_no', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->text('processing_note')->nullable();
            $table->json('audit_log')->nullable();
            $table->text('remark')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('withdrawal_no');
            $table->index('user_id');
            $table->index('wallet_id');
            $table->index('rule_id');
            $table->index('bank_card_id');
            $table->index('status');
            $table->index('currency');
            $table->index('withdrawal_method');
            $table->index('created_at');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('wallet_id')
                ->references('id')
                ->on('wallets')
                ->onDelete('set null');

            $table->foreign('rule_id')
                ->references('id')
                ->on('withdrawal_rules')
                ->onDelete('set null');

            $table->foreign('bank_card_id')
                ->references('id')
                ->on('bank_cards')
                ->onDelete('restrict');

            $table->foreign('approved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('processed_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
