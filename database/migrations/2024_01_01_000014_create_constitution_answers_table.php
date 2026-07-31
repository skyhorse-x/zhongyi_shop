<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constitution_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->comment('分析任务ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('question_id')->comment('题目ID');
            $table->string('answer', 20)->comment('答案:A B C D');
            $table->json('scores')->nullable()->comment('各体质得分');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('task_id')->references('id')->on('analysis_tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('constitution_questions')->onDelete('cascade');
            $table->index('task_id');
            $table->index('user_id');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constitution_answers');
    }
};
