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
        // 如果所有字段都已存在，直接返回
        if (Schema::hasColumn('admins', 'is_customer_service') &&
            Schema::hasColumn('admins', 'nickname') &&
            Schema::hasColumn('admins', 'avatar') &&
            Schema::hasColumn('admins', 'max_concurrent_sessions') &&
            Schema::hasColumn('admins', 'current_session_count') &&
            Schema::hasColumn('admins', 'status') &&
            Schema::hasColumn('admins', 'last_online_at')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            if (!Schema::hasColumn('admins', 'is_customer_service')) {
                $table->boolean('is_customer_service')->default(false)->comment('是否客服人员')->after('password');
            }
            if (!Schema::hasColumn('admins', 'nickname')) {
                $table->string('nickname', 50)->nullable()->comment('客服昵称')->after('is_customer_service');
            }
            if (!Schema::hasColumn('admins', 'avatar')) {
                $table->string('avatar', 255)->nullable()->comment('客服头像')->after('nickname');
            }
            if (!Schema::hasColumn('admins', 'max_concurrent_sessions')) {
                $table->unsignedInteger('max_concurrent_sessions')->default(10)->comment('最大并发会话数')->after('avatar');
            }
            if (!Schema::hasColumn('admins', 'current_session_count')) {
                $table->unsignedInteger('current_session_count')->default(0)->comment('当前会话数')->after('max_concurrent_sessions');
            }
            if (!Schema::hasColumn('admins', 'status')) {
                $table->string('status', 20)->default('offline')->comment('客服状态:offline离线 online在线 busy忙碌 away离开')->after('current_session_count');
            }
            if (!Schema::hasColumn('admins', 'last_online_at')) {
                $table->timestamp('last_online_at')->nullable()->comment('最后在线时间')->after('status');
            }
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
