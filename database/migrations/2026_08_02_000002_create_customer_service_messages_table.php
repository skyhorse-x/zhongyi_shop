<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 客服消息表
     * 记录每条聊天消息，支持文本、图片、附件、链接等类型
     */
    public function up(): void
    {
        if (Schema::hasTable('customer_service_messages')) {
            return;
        }
        Schema::create('customer_service_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->comment('会话ID');
            $table->string('sender_type', 20)->comment('发送者类型:user用户 staff客服 system系统');
            $table->unsignedBigInteger('sender_id')->comment('发送者ID');
            $table->string('message_type', 20)->default('text')->comment('消息类型:text文本 image图片 file附件 link链接');
            $table->text('content')->comment('消息内容(文本消息直接存,链接消息存链接地址)');
            $table->string('file_name', 255)->nullable()->comment('文件名(附件类型)');
            $table->string('file_path', 500)->nullable()->comment('文件存储路径(图片/附件)');
            $table->string('file_url', 500)->nullable()->comment('文件访问URL');
            $table->unsignedInteger('file_size')->nullable()->comment('文件大小(bytes)');
            $table->string('file_mime', 100)->nullable()->comment('文件MIME类型');
            $table->string('link_url', 500)->nullable()->comment('链接地址(链接类型)');
            $table->string('link_title', 200)->nullable()->comment('链接标题');
            $table->string('thumbnail_url', 500)->nullable()->comment('缩略图URL');
            $table->string('status', 20)->default('sent')->comment('状态:sent已发送 delivered已送达 read已读');
            $table->timestamp('read_at')->nullable()->comment('已读时间');
            $table->timestamps();

            $table->index('session_id');
            $table->index(['session_id', 'created_at']);
            $table->index('sender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service_messages');
    }
};
