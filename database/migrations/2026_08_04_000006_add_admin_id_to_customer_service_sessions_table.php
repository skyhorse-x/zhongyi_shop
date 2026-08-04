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
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            // 添加 admin_id 列（如果不存在）
            if (!Schema::hasColumn('customer_service_sessions', 'admin_id')) {
                $table->unsignedInteger('admin_id')->default(0)->after('user_id')->comment('客服管理员ID(0表示未分配)');
                $table->index('admin_id');
            }
            // 添加 message_count 列（如果不存在）
            if (!Schema::hasColumn('customer_service_sessions', 'message_count')) {
                $table->unsignedInteger('message_count')->default(0)->after('status')->comment('消息数量');
            }
            // 添加 user_unread 列（如果不存在）
            if (!Schema::hasColumn('customer_service_sessions', 'user_unread')) {
                $table->unsignedInteger('user_unread')->default(0)->after('message_count')->comment('用户未读数');
            }
            // 添加 admin_unread 列（如果不存在）
            if (!Schema::hasColumn('customer_service_sessions', 'admin_unread')) {
                $table->unsignedInteger('admin_unread')->default(0)->after('user_unread')->comment('客服未读数');
            }
            // 添加 last_message_at 列（如果不存在）
            if (!Schema::hasColumn('customer_service_sessions', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('admin_unread')->comment('最后消息时间');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->dropColumn(['admin_id', 'message_count', 'user_unread', 'admin_unread', 'last_message_at']);
        });
    }
};
