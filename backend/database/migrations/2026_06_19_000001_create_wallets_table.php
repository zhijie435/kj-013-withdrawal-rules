<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10)->default('CNY');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('frozen_amount', 15, 2)->default(0);
            $table->decimal('pending_settle_amount', 15, 2)->default(0);
            $table->decimal('total_withdrawn', 15, 2)->default(0);
            $table->decimal('total_recharge', 15, 2)->default(0);
            $table->decimal('today_withdrawn', 15, 2)->default(0);
            $table->date('last_withdraw_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
