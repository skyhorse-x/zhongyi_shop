<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 用户反馈 + AI 申诉
 */
return new class extends Migration {
    public function up(): void
    {
        // 用户反馈（表已存在则跳过，避免历史环境重复建表冲突）
        if (!Schema::hasTable('feedbacks')) {
            Schema::create('feedbacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('type', ['bug', 'suggestion', 'complaint', 'other'])->default('other');
                $table->string('title', 200);
                $table->text('content');
                $table->json('images')->nullable()->comment('截图 URL 数组');
                $table->string('contact', 100)->nullable()->comment('联系方式');
                $table->enum('status', ['pending', 'processing', 'replied', 'closed'])->default('pending');
                $table->text('reply')->nullable();
                $table->timestamp('replied_at')->nullable();
                $table->foreignId('replied_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }

        // AI 诊断申诉
        if (!Schema::hasTable('analysis_appeals')) {
            Schema::create('analysis_appeals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('analysis_id')->nullable()->constrained('analysis_tasks')->nullOnDelete();
                $table->string('task_no', 64)->nullable()->comment('冗余字段');
                $table->string('reason', 200)->comment('申诉原因分类');
                $table->text('description')->comment('详细说明');
                $table->json('attachments')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->text('audit_note')->nullable()->comment('审核意见');
                $table->foreignId('audited_by')->nullable()->constrained('admins')->nullOnDelete();
                $table->timestamp('audited_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_appeals');
        Schema::dropIfExists('feedbacks');
    }
};
