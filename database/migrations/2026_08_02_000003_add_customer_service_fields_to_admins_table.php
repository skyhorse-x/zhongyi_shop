<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 为admins表增加客服相关字段
     */
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->boolean('is_customer_service')->default(false)->comment('是否客服人员')->after('password');
            $table->string('nickname', 50)->nullable()->comment('客服昵称')->after('is_customer_service');
            $table->string('avatar', 255)->nullable()->comment('客服头像')->after('nickname');
            $table->unsignedInteger('max_concurrent_sessions')->default(10)->comment('最大并发会话数')->after('avatar');
            $table->unsignedInteger('current_session_count')->default(0)->comment('当前会话数')->after('max_concurrent_sessions');
            $table->string('status', 20)->default('offline')->comment('客服状态:offline离线 online在线 busy忙碌 away离开')->after('current_session_count');
            $table->timestamp('last_online_at')->nullable()->comment('最后在线时间')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn([
                'is_customer_service',
                'nickname',
                'avatar',
                'max_concurrent_sessions',
                'current_session_count',
                'status',
                'last_online_at',
            ]);
        });
    }
};
