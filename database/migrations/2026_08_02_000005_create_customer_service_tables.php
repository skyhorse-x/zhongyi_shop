<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 客服会话和消息表
     */
    public function up(): void
    {
        // 客服会话表
        Schema::create('customer_service_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_no', 32)->unique()->comment('会话编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('admin_id')->default(0)->comment('客服管理员ID(0表示未分配)');
            $table->string('title', 100)->default('')->comment('会话标题');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态:0待接入 1服务中 2已关闭');
            $table->unsignedInteger('message_count')->default(0)->comment('消息数量');
            $table->unsignedInteger('user_unread')->default(0)->comment('用户未读数');
            $table->unsignedInteger('admin_unread')->default(0)->comment('客服未读数');
            $table->timestamp('last_message_at')->nullable()->comment('最后消息时间');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('admin_id');
            $table->index('status');
            $table->index('created_at');
        });

        // 客服消息表
        Schema::create('customer_service_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->comment('会话ID');
            $table->unsignedBigInteger('sender_id')->comment('发送者ID');
            $table->string('sender_type', 20)->comment('发送者类型:user用户 admin客服');
            $table->text('content')->default('')->comment('消息内容');
            $table->string('msg_type', 20)->default('text')->comment('消息类型:text文本 image图片 file文件');
            $table->string('file_url', 500)->default('')->comment('文件URL');
            $table->string('file_name', 255)->default('')->comment('文件名称');
            $table->unsignedInteger('file_size')->default(0)->comment('文件大小(字节)');
            $table->timestamps();
            
            $table->index('session_id');
            $table->index('sender_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_service_messages');
        Schema::dropIfExists('customer_service_sessions');
    }
};
