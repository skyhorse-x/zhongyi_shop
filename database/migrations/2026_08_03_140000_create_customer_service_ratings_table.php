<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 客服会话评价
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_service_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('session_no', 64)->unique()->comment('会话编号（与 sessions 一一对应）');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->tinyInteger('score')->comment('评分 1-5');
            $table->enum('attitude', ['good', 'normal', 'bad'])->default('normal')->comment('态度');
            $table->enum('solved', ['yes', 'partial', 'no'])->default('yes')->comment('是否解决');
            $table->text('comment')->nullable();
            $table->json('tags')->nullable()->comment('标签数组');
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['score', 'created_at']);
        });

        // 扩展 sessions 表
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->tinyInteger('satisfaction_score')->nullable()->after('closed_at')->comment('满意度评分');
            $table->boolean('rated')->default(false)->after('satisfaction_score')->comment('是否已评价');
        });
    }

    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->dropColumn(['satisfaction_score', 'rated']);
        });
        Schema::dropIfExists('customer_service_ratings');
    }
};
