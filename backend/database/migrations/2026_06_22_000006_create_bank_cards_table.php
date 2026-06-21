<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('card_type', 30)->default('debit');
            $table->string('bank_name', 100);
            $table->string('bank_code', 50)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('card_number', 50);
            $table->string('card_holder_name', 100);
            $table->string('currency', 10)->default('CNY');
            $table->string('province', 50)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_id');
            $table->index('card_type');
            $table->index('is_active');
            $table->index(['user_id', 'is_default']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_cards');
    }
};
