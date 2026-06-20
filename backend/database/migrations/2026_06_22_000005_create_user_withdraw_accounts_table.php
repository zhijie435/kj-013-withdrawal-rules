<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_withdraw_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('withdraw_method_id');
            $table->string('account_name', 100);
            $table->string('account_number', 100);
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->string('qr_code', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('status')->default(true);
            $table->text('remark')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('withdraw_method_id');
            $table->index(['user_id', 'status']);

            $table->foreign('withdraw_method_id')
                ->references('id')
                ->on('withdraw_methods')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_withdraw_accounts');
    }
};
