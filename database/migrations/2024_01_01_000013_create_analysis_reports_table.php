<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->unique()->comment('任务ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('type', 20)->comment('报告类型:tongue face constitution');
            $table->tinyInteger('health_score')->nullable()->comment('健康评分(0-100)');
            $table->string('tongue_color', 50)->nullable()->comment('舌色');
            $table->string('tongue_shape', 100)->nullable()->comment('舌形');
            $table->string('tongue_coating', 100)->nullable()->comment('舌苔');
            $table->string('sublingual_vein', 100)->nullable()->comment('舌下络脉');
            $table->text('tongue_analysis')->nullable()->comment('舌象分析详情');
            $table->string('face_color', 50)->nullable()->comment('面色');
            $table->string('lip_color', 50)->nullable()->comment('唇色');
            $table->string('eye_analysis', 200)->nullable()->comment('眼部分析');
            $table->string('skin_analysis', 200)->nullable()->comment('皮肤分析');
            $table->text('face_analysis')->nullable()->comment('面诊分析详情');
            $table->string('constitution_type', 20)->nullable()->comment('体质类型');
            $table->text('constitution_analysis')->nullable()->comment('体质分析详情');
            $table->text('life_advice')->nullable()->comment('生活建议');
            $table->text('diet_advice')->nullable()->comment('饮食建议');
            $table->text('exercise_advice')->nullable()->comment('运动建议');
            $table->text('precautions')->nullable()->comment('注意事项');
            $table->string('summary', 500)->nullable()->comment('摘要(未付费可见)');
            $table->json('content')->nullable()->comment('完整报告内容(付费可见)');
            $table->tinyInteger('is_paid')->default(0)->comment('是否已付费:0否 1是');
            $table->dateTime('viewed_at')->nullable()->comment('查看时间');
            $table->timestamps();
            $table->foreign('task_id')->references('id')->on('analysis_tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('type');
            $table->index('is_paid');
            $table->index('constitution_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_reports');
    }
};
