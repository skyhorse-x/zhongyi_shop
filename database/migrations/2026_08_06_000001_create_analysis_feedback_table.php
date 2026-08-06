<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analysis_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('用户ID');
            $table->foreignId('task_id')->constrained('analysis_tasks')->comment('分析任务ID');
            $table->string('type', 20)->comment('反馈类型: useful, useless');
            $table->tinyInteger('rating')->nullable()->comment('评分 1-5');
            $table->timestamps();

            $table->index('user_id', 'idx_user_id');
            $table->index('task_id', 'idx_task_id');
            $table->unique(['user_id', 'task_id'], 'uk_user_task');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_feedback');
    }
};
