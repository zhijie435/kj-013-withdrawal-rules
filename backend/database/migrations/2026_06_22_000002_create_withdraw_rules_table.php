<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('user_level', 50)->default('normal');
            $table->unsignedBigInteger('withdraw_method_id');
            $table->decimal('min_amount', 15, 2)->default(0);
            $table->decimal('max_amount', 15, 2)->default(0);
            $table->decimal('daily_max_amount', 15, 2)->default(0);
            $table->integer('daily_max_count')->default(0);
            $table->decimal('monthly_max_amount', 15, 2)->default(0);
            $table->integer('monthly_max_count')->default(0);
            $table->decimal('fee_rate', 5, 4)->default(0);
            $table->decimal('fixed_fee', 10, 2)->default(0);
            $table->decimal('min_fee', 10, 2)->default(0);
            $table->decimal('max_fee', 10, 2)->default(0);
            $table->integer('processing_days')->default(1);
            $table->boolean('requires_audit')->default(true);
            $table->boolean('status')->default(true);
            $table->text('remark')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_level', 'withdraw_method_id']);
            $table->index('status');
            $table->index(['user_level', 'status']);

            $table->foreign('withdraw_method_id')
                ->references('id')
                ->on('withdraw_methods')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_rules');
    }
};
