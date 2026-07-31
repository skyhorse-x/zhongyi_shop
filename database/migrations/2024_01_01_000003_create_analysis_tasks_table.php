<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_no', 32)->unique()->comment('任务编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('type', 20)->comment('类型:tongue舌诊 face面诊 constitution体质');
            $table->string('image_url', 500)->nullable()->comment('原始图片URL');
            $table->string('image_md5', 32)->nullable()->comment('图片MD5(用于缓存)');
            $table->tinyInteger('status')->default(0)->comment('状态:0待处理 1处理中 2完成 3失败');
            $table->string('model', 50)->nullable()->comment('使用的AI模型');
            $table->text('prompt')->nullable()->comment('使用的Prompt');
            $table->integer('tokens')->default(0)->comment('消耗Token数');
            $table->decimal('cost', 10, 4)->default(0)->comment('AI调用成本(元)');
            $table->json('result')->nullable()->comment('AI返回结果');
            $table->string('error_msg', 500)->nullable()->comment('错误信息');
            $table->tinyInteger('is_paid')->default(0)->comment('是否已支付');
            $table->timestamp('started_at')->nullable()->comment('开始处理时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('image_md5');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_tasks');
    }
};
