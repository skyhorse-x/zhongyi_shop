<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('model_id')->comment('模型ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->unsignedBigInteger('task_id')->nullable()->comment('任务ID');
            $table->string('type', 20)->nullable()->comment('调用类型');
            $table->integer('prompt_tokens')->default(0)->comment('提示词Token数');
            $table->integer('completion_tokens')->default(0)->comment('完成Token数');
            $table->integer('total_tokens')->default(0)->comment('总Token数');
            $table->decimal('cost', 10, 4)->default(0)->comment('成本(元)');
            $table->integer('response_time')->default(0)->comment('响应时间(ms)');
            $table->tinyInteger('status')->default(1)->comment('状态:1成功 0失败');
            $table->text('error')->nullable()->comment('错误信息');
            $table->timestamp('created_at')->useCurrent();
            $table->index('model_id');
            $table->index('user_id');
            $table->index('task_id');
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
