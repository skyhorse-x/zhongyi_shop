<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_service_sessions', 'user_unread')) {
                $table->unsignedInteger('user_unread')->default(0)->after('admin_unread')->comment('用户未读消息数');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_service_sessions', 'user_unread')) {
                $table->dropColumn('user_unread');
            }
        });
    }
};
