<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 客服会话表
     * 记录每个用户与客服的对话会话
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_service_sessions')) {
            return;
        }
        Schema::create('customer_service_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_no', 32)->unique()->comment('会话编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('staff_id')->nullable()->comment('客服人员ID( admins表 )');
            $table->string('title', 100)->default('咨询会话')->comment('会话标题');
            $table->string('status', 20)->default('waiting')->comment('状态:waiting待接待 active进行中 resolved已解决 closed已关闭');
            $table->string('source', 20)->default('web')->comment('来源:web网页 mobile移动端');
            $table->ipAddress('user_ip')->nullable()->comment('用户IP');
            $table->string('user_agent', 500)->nullable()->comment('用户浏览器UA');
            $table->unsignedInteger('user_message_count')->default(0)->comment('用户消息计数');
            $table->unsignedInteger('staff_message_count')->default(0)->comment('客服消息计数');
            $table->timestamp('user_last_message_at')->nullable()->comment('用户最后消息时间');
            $table->timestamp('staff_last_message_at')->nullable()->comment('客服最后消息时间');
            $table->timestamp('assigned_at')->nullable()->comment('分配客服时间');
            $table->timestamp('resolved_at')->nullable()->comment('解决时间');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('staff_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service_sessions');
    }
};
