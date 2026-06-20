<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_no', 64)->unique();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 32)->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->string('currency', 10)->default('CNY');
            $table->nullableMorphs('related');
            $table->string('description')->nullable();
            $table->string('status', 32)->default('completed');
            $table->foreignId('operator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
