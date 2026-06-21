<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('code', 50)->unique();
            $table->string('user_level', 30)->default('all');
            $table->string('currency', 10)->default('CNY');
            $table->string('withdrawal_method', 50)->default('bank_transfer');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->default(0);
            $table->decimal('daily_limit', 15, 2)->default(0);
            $table->decimal('monthly_limit', 15, 2)->default(0);
            $table->decimal('fee_rate', 7, 4)->default(0);
            $table->decimal('fee_min', 10, 2)->default(0);
            $table->decimal('fee_max', 10, 2)->default(0);
            $table->integer('settlement_days')->default(1);
            $table->integer('daily_max_count')->default(0);
            $table->boolean('require_approval')->default(true);
            $table->decimal('approval_threshold', 15, 2)->default(0);
            $table->json('allowed_regions')->nullable();
            $table->json('denied_regions')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('user_level');
            $table->index('currency');
            $table->index('withdrawal_method');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['user_level', 'currency', 'withdrawal_method', 'is_active']);
            $table->index(['effective_from', 'effective_to']);

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
        Schema::dropIfExists('withdrawal_rules');
    }
};
