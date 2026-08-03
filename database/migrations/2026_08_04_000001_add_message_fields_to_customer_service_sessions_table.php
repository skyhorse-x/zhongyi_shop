<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_service_sessions', 'message_count')) {
                $table->unsignedInteger('message_count')->default(0)->after('source')->comment('消息数量');
            }
            if (!Schema::hasColumn('customer_service_sessions', 'admin_unread')) {
                $table->unsignedInteger('admin_unread')->default(0)->after('message_count')->comment('客服未读消息数');
            }
            if (!Schema::hasColumn('customer_service_sessions', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable()->after('admin_unread')->comment('最后消息时间');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_service_sessions', 'last_message_at')) {
                $table->dropColumn('last_message_at');
            }
            if (Schema::hasColumn('customer_service_sessions', 'admin_unread')) {
                $table->dropColumn('admin_unread');
            }
            if (Schema::hasColumn('customer_service_sessions', 'message_count')) {
                $table->dropColumn('message_count');
            }
        });
    }
};
