<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('icon', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('currency', 10)->default('CNY');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('status')->default(true);
            $table->json('config')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index(['sort', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_methods');
    }
};
