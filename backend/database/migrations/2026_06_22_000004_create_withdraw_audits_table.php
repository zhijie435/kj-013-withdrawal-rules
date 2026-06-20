<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('withdraw_request_id');
            $table->unsignedBigInteger('auditor_id')->nullable();
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('remark')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('withdraw_request_id');
            $table->index('auditor_id');
            $table->index('action');
            $table->index(['withdraw_request_id', 'created_at']);

            $table->foreign('withdraw_request_id')
                ->references('id')
                ->on('withdraw_requests')
                ->onDelete('cascade');

            $table->foreign('auditor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_audits');
    }
};
